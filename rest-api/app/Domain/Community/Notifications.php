<?php

namespace App\Domain\Community;

use App\Exceptions\UnknownOutboxEvent;
use App\Models\Agenda;
use App\Models\Akun;
use App\Models\AlokasiPembayaran;
use App\Models\Aspirasi;
use App\Models\KejadianDarurat;
use App\Models\KonfirmasiIuran;
use App\Models\OutboxEvent;
use App\Models\Pengaduan;
use App\Models\Pengumuman;
use App\Models\PermohonanAkun;
use App\Models\UsulanKegiatan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Konsumen outbox → inbox notifikasi (FR20, BR21), dipanggil job ProcessOutboxEvent setelah
 * OutboxDispatcher mengirim ID event ke antrean. Idempotent: UNIQUE(event_id, penerima_id)
 * sehingga replay event tidak menggandakan notifikasi. Teks aman: tidak memuat identitas pelapor
 * anonim, data keluarga, atau nominal pribadi rumah lain. Push FCM menyusul setelah commit lewat
 * job SendPush (M10); kegagalan push tidak mengubah inbox maupun event.
 */
final class Notifications
{
    public static function handle(OutboxEvent $event): void
    {
        $id = $event->aggregate_id;
        match ($event->type) {
            'pengaduan.dibuat' => self::toPengurus($event, 'pengaduan', ['pengaduan_id' => $id], 'Pengaduan baru', 'Ada pengaduan baru yang perlu ditindaklanjuti.'),
            'darurat.dibuat' => self::emergency($event),
            'darurat.ditangani' => self::emergencyResolution($event),
            'darurat.penyalahgunaan_dibatalkan' => ($e = KejadianDarurat::find($id)) && self::send($event, [$e->pelapor_id], 'sistem', [],
                'Penanda penyalahgunaan dibatalkan', 'Pengurus membatalkan penanda penyalahgunaan pada salah satu laporan darurat Anda. Hitungan penyalahgunaan sudah diperbarui.'),
            'pengaduan.status_berubah' => ($p = Pengaduan::find($id)) && self::send($event, [$p->pelapor_id], 'pengaduan', ['pengaduan_id' => $id],
                'Status pengaduan diperbarui', 'Pengaduan "'.Str::limit($p->judul, 60).'" kini berstatus '.self::label(self::eventStatus($event, $p->status)).'.'),
            'aspirasi.dibuat' => self::toPengurus($event, 'aspirasi', ['aspirasi_id' => $id], 'Aspirasi baru', 'Ada aspirasi baru dari warga.'),
            'aspirasi.status_berubah' => ($a = Aspirasi::find($id)) && self::send($event, [$a->pengirim_id], 'aspirasi', ['aspirasi_id' => $id],
                'Status aspirasi diperbarui', 'Aspirasi "'.Str::limit($a->judul, 60).'" kini berstatus '.self::label(self::eventStatus($event, $a->status)).'.'),
            'usulan.diajukan' => self::toPengurus($event, 'usulan', ['usulan_kegiatan_id' => $id], 'Usulan kegiatan baru', 'Ada usulan kegiatan warga yang menunggu tinjauan.'),
            'usulan.diperbaiki' => self::toPengurus($event, 'usulan', ['usulan_kegiatan_id' => $id], 'Usulan kegiatan diperbaiki', 'Pengusul memperbaiki usulan kegiatan. Tinjau ulang.'),
            'usulan.status_berubah' => self::proposalStatus($event),
            'pengumuman.terbit' => ($p = Pengumuman::find($id)) && self::send($event, self::activeResidents(), 'pengumuman', ['pengumuman_id' => $id],
                Str::limit($p->judul, 150), Str::limit(strip_tags($p->isi), 180)),
            'agenda.dibuat', 'agenda.diubah', 'agenda.pengingat' => self::agenda($event),
            // Audit M16: pengurus tahu setiap akun utama baru agar akun asing bisa segera dinonaktifkan.
            'akun.registrasi_utama' => self::toPengurus($event, 'sistem', [], 'Akun utama baru', 'Warga baru mendaftar sebagai akun utama. Cocokkan dengan data RT; nonaktifkan bila tidak dikenal.'),
            'token.dirotasi_otomatis' => self::toPengurus($event, 'sistem', [], 'Token registrasi diganti', 'Terlalu banyak percobaan token salah, jadi token registrasi diganti otomatis. Bagikan token baru dari menu Token registrasi.'),
            // ADR43: akun target diberi tahu perubahan perannya (token lama sudah dicabut; masuk ulang).
            'akun.peran_diubah' => self::send($event, [$id], 'sistem', [], ($event->safe_payload['peran'] ?? '') === 'pengurus' ? 'Anda menjadi pengurus' : 'Peran pengurus dicabut',
                ($event->safe_payload['peran'] ?? '') === 'pengurus' ? 'Super admin menjadikan akun Anda pengurus RT05. Masuk kembali untuk membuka menu pengurus.' : 'Akun Anda kembali menjadi akun warga. Masuk kembali untuk melanjutkan.'),
            'akun.permohonan_tambahan' => self::toPengurus($event, 'sistem', [], 'Permohonan akun tambahan', 'Ada permohonan akun tambahan yang menunggu persetujuan.'),
            'akun.permohonan_disetujui' => self::send($event, [$id], 'sistem', [], 'Akun disetujui', 'Akun Anda telah disetujui pengurus. Selamat datang di RT05 TAKEDA.'),
            'iuran.pembayaran_dicatat' => self::paymentRecorded($event),
            // ADR35: bukti transfer warga → pengurus; hasil pemeriksaan → pengirim. Tanpa nominal/rumah di teks.
            'iuran.konfirmasi_dikirim' => self::toPengurus($event, 'iuran', [], 'Bukti pembayaran iuran', 'Warga mengirim bukti pembayaran iuran. Periksa lalu catat pembayarannya.'),
            'iuran.konfirmasi_diproses' => ($k = KonfirmasiIuran::find($id)) && self::send($event, [$k->akun_id], 'iuran', [],
                'Bukti pembayaran diperiksa', ($event->safe_payload['status'] ?? $k->status) === 'ditolak'
                    ? 'Bukti pembayaran iuran Anda belum dapat diterima. Lihat catatan pengurus di menu Iuran.'
                    : 'Pengurus sudah memeriksa bukti pembayaran iuran Anda.'),
            // Siaran operator (rt05:siaran-sistem): kebijakan layanan atau pembaruan aplikasi.
            'sistem.siaran' => self::send($event, self::activeResidents(), 'sistem', [],
                Str::limit((string) ($event->safe_payload['judul'] ?? 'Informasi RT05 TAKEDA'), 150), Str::limit((string) ($event->safe_payload['isi'] ?? ''), 500)),
            // Event sintetis: rt05:async-smoke (penerima akun sintetis, database *_test) atau
            // rt05:staging-smoke (penerima kosong → tanpa inbox).
            'rt05.smoke' => self::send($event, $event->safe_payload['penerima'] ?? [], 'sistem', [], 'Uji antrean', 'Notifikasi sintetis uji worker.'),
            'iuran.pengingat' => self::send($event, $event->safe_payload['penerima'] ?? [], 'iuran', ['tagihan_id' => $event->safe_payload['tagihan_id'] ?? null],
                'Pengingat iuran', 'Iuran RT bulan ini belum tercatat. Abaikan bila sudah membayar kepada pengurus.'),
            // Dicatat untuk audit/integrasi berikutnya; v1 sengaja tanpa notifikasi inbox.
            'iuran.tagihan_dibuat', 'iuran.pembayaran_dibalik' => null,
            // M13: invalidasi cache konten publik dibaca website (M14); tanpa notifikasi inbox.
            'konten.diterbitkan', 'konten.diarsipkan' => null,
            default => throw new UnknownOutboxEvent($event->type),
        };
    }

    /**
     * Status saat transisi terjadi (payload event), bukan status terkini: event yang diproses
     * terlambat setelah beberapa transisi tetap memberi tahu langkah masing-masing.
     */
    private static function emergency(OutboxEvent $event): bool
    {
        Emergencies::lock();
        $e = KejadianDarurat::find($event->aggregate_id);
        if (! $e || ! Emergencies::alarmEligible($e)) {
            return false;
        }

        return self::send($event, Emergencies::recipients($event), 'darurat', ['kejadian_darurat_id' => $e->id],
            'Darurat RT05 TAKEDA', 'Ada laporan darurat warga. Buka aplikasi untuk melihat jenis kejadian dan rumah pelapor.');
    }

    private static function emergencyResolution(OutboxEvent $event): bool
    {
        $e = KejadianDarurat::find($event->aggregate_id);
        if (! $e) {
            return false;
        }

        return self::send($event, [$e->pelapor_id], 'sistem', [],
            $e->penyalahgunaan ? 'Peringatan tombol panik' : 'Laporan darurat ditangani',
            $e->penyalahgunaan ? 'Pengurus mencatat penyalahgunaan pada laporan Anda. Setelah tiga penyalahgunaan tombol panik akun dinonaktifkan. Periksa hitungan di menu Tombol panik atau hubungi pengurus.'
                : 'Pengurus sudah memperbarui penanganan laporan darurat Anda. Buka menu Tombol panik untuk melihat statusnya.');
    }

    private static function eventStatus(OutboxEvent $event, string $current): string
    {
        return (string) ($event->safe_payload['status'] ?? $current);
    }

    /** Pengusul diberi tahu tiap keputusan; persetujuan menyebut bahwa usulan sudah menjadi agenda. */
    private static function proposalStatus(OutboxEvent $event): bool
    {
        $u = UsulanKegiatan::find($event->aggregate_id);
        if (! $u) {
            return false;
        }
        $status = self::eventStatus($event, $u->status);
        $nama = Str::limit($u->nama, 60);
        [$title, $body] = $status === 'disetujui'
            ? ['Usulan kegiatan disetujui', 'Usulan "'.$nama.'" disetujui dan masuk agenda RT. Semua warga mendapat pemberitahuan.']
            : ['Status usulan kegiatan diperbarui', 'Usulan "'.$nama.'" kini berstatus '.self::label($status).'. Buka detail untuk catatan pengurus.'];

        return self::send($event, [$u->pengusul_id], 'usulan', ['usulan_kegiatan_id' => $u->id], $title, $body);
    }

    private static function agenda(OutboxEvent $event): bool
    {
        $agenda = Agenda::find($event->aggregate_id);
        if (! $agenda || $agenda->archived_at) {
            return false;
        }
        $title = $event->type === 'agenda.diubah' ? 'Jadwal agenda berubah' : 'Agenda baru';
        // Basi: waktu agenda berubah lagi setelah event dicatat (event baru menyusul untuk waktu baru).
        $planned = $event->safe_payload['mulai_at'] ?? null;
        if ($planned && ! $agenda->mulai_at->equalTo(Carbon::parse($planned))) {
            return false;
        }
        if ($event->type === 'agenda.pengingat') {
            // Agenda sudah dimulai karena worker/cron sempat mati: pengingat tidak lagi berguna.
            if ($agenda->mulai_at->lte(now())) {
                return false;
            }
            $days = (int) now('Asia/Jakarta')->startOfDay()->diffInDays($agenda->mulai_at->copy()->setTimezone('Asia/Jakarta')->startOfDay());
            $title = $days === 0 ? 'Pengingat agenda hari ini' : 'Pengingat agenda besok';
        }
        $when = $agenda->mulai_at->copy()->setTimezone('Asia/Jakarta')->translatedFormat('l, d F Y H:i');

        return self::send($event, self::activeResidents(), 'agenda', ['agenda_id' => $agenda->id], $title, Str::limit($agenda->nama, 80).' — '.$when.' WIB, '.Str::limit($agenda->lokasi, 60).'.');
    }

    private static function paymentRecorded(OutboxEvent $event): bool
    {
        $alokasi = AlokasiPembayaran::where('pembayaran_id', $event->aggregate_id)->first();
        $periods = implode(', ', $event->safe_payload['periode'] ?? []);

        return self::send($event, self::primaryAccountsOf($event->safe_payload['rumah_id'] ?? null), 'iuran', ['tagihan_id' => $alokasi?->tagihan_id],
            'Pembayaran iuran tercatat', 'Pembayaran iuran periode '.$periods.' telah dicatat pengurus.');
    }

    private static function toPengurus(OutboxEvent $event, string $jenis, array $context, string $title, string $body): bool
    {
        return self::send($event, Akun::where('peran', 'pengurus')->where('status', 'aktif')->pluck('id')->all(), $jenis, $context, $title, $body);
    }

    /** @param iterable<string> $recipients */
    public static function send(OutboxEvent $event, iterable $recipients, string $jenis, array $context, string $title, string $body): bool
    {
        $rows = collect($recipients)->filter()->unique()->map(fn ($akunId) => array_merge([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'penerima_id' => $akunId,
            'jenis' => $jenis,
            'judul' => $title,
            'isi' => $body,
        ], $context))->values();
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('notifikasi')->insertOrIgnore($chunk->all());
        }

        return true;
    }

    public static function activeResidents(): Collection
    {
        return Akun::where('status', 'aktif')->pluck('id');
    }

    /**
     * Akun utama aktif pada keluarga yang menghuni rumah (penerima pengingat iuran, BR v1).
     * M07: dari tautan_akun aktif is_utama, bukan pointer akun.warga_id/jenis.
     */
    public static function primaryAccountsOf(?string $rumahId): Collection
    {
        if (! $rumahId) {
            return collect();
        }

        return DB::table('tautan_akun')
            ->join('akun', 'akun.id', '=', 'tautan_akun.akun_id')
            ->join('penghunian', 'penghunian.keluarga_id', '=', 'tautan_akun.keluarga_id')
            ->where('penghunian.rumah_id', $rumahId)
            ->whereNull('penghunian.selesai_tanggal')
            ->whereNull('tautan_akun.selesai_at')
            ->where('tautan_akun.is_utama', true)
            ->where('akun.status', 'aktif')
            ->pluck('akun.id');
    }

    public static function label(string $status): string
    {
        return [
            'diajukan' => 'Diajukan', 'diproses' => 'Diproses', 'ditinjau' => 'Ditinjau', 'selesai' => 'Selesai',
            'perlu_revisi' => 'Perlu revisi', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak',
        ][$status] ?? $status;
    }

    public static function pendingRequests(): int
    {
        return PermohonanAkun::where('status', 'diajukan')->count();
    }
}
