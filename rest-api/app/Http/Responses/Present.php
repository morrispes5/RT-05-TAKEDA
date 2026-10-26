<?php

namespace App\Http\Responses;

use App\Domain\Community\Calendar;
use App\Domain\Residents\AgeGroups;
use App\Models\Agenda;
use App\Models\Akun;
use App\Models\Aspirasi;
use App\Models\KeanggotaanKeluarga;
use App\Models\Keluarga;
use App\Models\KonfirmasiIuran;
use App\Models\Notifikasi;
use App\Models\PembayaranIuran;
use App\Models\Pengaduan;
use App\Models\Penghunian;
use App\Models\Pengumuman;
use App\Models\PermohonanAkun;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Models\TautanAkun;
use App\Models\TransaksiKas;
use App\Models\UsulanKegiatan;
use App\Models\Warga;
use App\Support\Privacy;
use DateTimeInterface;

/**
 * Serializer allowlist. Setiap field yang keluar dari API ditulis eksplisit di sini.
 * Anonimitas (BR09): bila sembunyikan_identitas, warga lain tidak menerima pelapor_id/nama;
 * pemilik melihat laporannya sendiri; pengurus selalu melihat pelapor.
 */
final class Present
{
    public static function ts(?DateTimeInterface $t): ?string
    {
        // Selalu dikonversi ke UTC: model yang baru diisi dari input "+07:00" belum dimuat ulang dari
        // database, sehingga format langsung akan memberi jam WIB berlabel Z (gate timezone M10).
        return $t ? \DateTimeImmutable::createFromInterface($t)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') : null;
    }

    /** Profil akun. Keluarga/rumah/hak ubah dari tautan aktif (M07), bukan pointer warga_id. */
    public static function akun(Akun $a): array
    {
        $rumah = $a->rumah();

        return [
            'id' => $a->id,
            'nama' => $a->nama,
            'email' => $a->email,
            'peran' => $a->peran,
            'versi_keuangan' => config('finance.v2') ? 2 : 1,
            'jenis' => $a->jenis,
            'status' => $a->status,
            'rumah' => $rumah ? ['id' => $rumah->id, 'kode_rumah' => $rumah->kode_rumah, 'alamat' => $rumah->alamat()] : null,
            'keluarga_id' => $a->keluarga()?->id,
            'dapat_ubah_keluarga' => $a->isAktif() && ($a->isUtama() || $a->isPengurus()),
            // ADR35: aplikasi menampilkan Kebijakan Privasi sampai versi terbaru disetujui.
            // ADR43: tombol angkat/turunkan pengurus hanya tampil untuk super admin.
            'super_admin' => $a->isSuperAdmin(),
            'privasi_versi' => Privacy::VERSION,
            'privasi_perlu_setuju' => Privacy::needsConsent($a),
        ];
    }

    public static function warga(Warga $w): array
    {
        return [
            'id' => $w->id,
            'nama' => $w->nama,
            'hubungan_keluarga' => $w->hubungan_keluarga,
            'is_kepala' => $w->is_kepala,
            'whatsapp' => $w->whatsapp,
            // ADR35: NIK hanya tersamar (4 digit awal + 4 akhir); nilai utuh tidak pernah keluar dari server.
            'nik_tersamar' => Privacy::mask($w->nik),
            // ADR42: tanggal lahir opsional; umur dan kelompok dihitung per hari ini (WIB), tidak disimpan.
            'tanggal_lahir' => $w->tanggal_lahir?->format('Y-m-d'),
            'umur' => $umur = AgeGroups::age($w->tanggal_lahir),
            'kelompok_usia' => AgeGroups::group($umur),
            'status' => $w->status,
            'punya_akun' => match (true) {
                $w->relationLoaded('tautanAktif') => (bool) $w->tautanAktif,
                $w->relationLoaded('akun') => (bool) $w->akun,
                default => null,
            },
        ];
    }

    /** Relasi eager-load untuk keluarga(): anggota dari keanggotaan aktif. */
    public const KELUARGA = ['keanggotaanAktif.warga.tautanAktif', 'penghunianAktif.rumah'];

    /** Keluarga + anggota aktif (keanggotaan M07). $dapatUbah diisi untuk GET /household. */
    public static function keluarga(Keluarga $k, ?Rumah $rumah, ?bool $dapatUbah = null): array
    {
        $anggota = $k->keanggotaanAktif->map->warga->filter()
            ->sortBy(fn (Warga $w) => [$w->is_kepala ? 0 : 1, $w->nama])->values();

        return [
            'id' => $k->id,
            'status' => $k->status,
            'versi' => $k->versi,
            'rumah' => $rumah ? self::rumah($rumah) : null,
            'jenis_hunian' => $k->penghunianAktif?->jenis_hunian,
            'nomor_kk_tersamar' => Privacy::mask($k->nomor_kk),
            'avatar' => $k->avatar,
            'anggota' => $anggota->map(fn ($w) => self::warga($w))->all(),
        ] + ($dapatUbah === null ? [] : ['dapat_ubah' => $dapatUbah]);
    }

    /** Ringkasan keluarga untuk daftar pengurus. */
    public static function keluargaRingkas(Keluarga $k): array
    {
        $rumah = $k->penghunianAktif?->rumah;
        $anggota = $k->keanggotaanAktif->map->warga->filter();

        return [
            'id' => $k->id,
            'status' => $k->status,
            'versi' => $k->versi,
            'kepala' => $anggota->firstWhere('is_kepala', true)?->nama,
            'avatar' => $k->avatar,
            'jumlah_anggota' => $anggota->count(),
            'rumah' => $rumah ? ['id' => $rumah->id, 'kode_rumah' => $rumah->kode_rumah, 'alamat' => $rumah->alamat()] : null,
            'archived_at' => self::ts($k->archived_at),
        ];
    }

    public const KELUARGA_DETAIL = [
        'keanggotaanAktif.warga.tautanAktif', 'penghunianAktif.rumah', 'penghunian.rumah',
        'keanggotaan.warga', 'tautan.akun', 'tautan.warga',
    ];

    /** Detail keluarga untuk pengurus: anggota aktif + histori hunian, keanggotaan dan tautan akun. */
    public static function keluargaDetail(Keluarga $k): array
    {
        return self::keluarga($k, $k->penghunianAktif?->rumah) + [
            'archived_at' => self::ts($k->archived_at),
            'created_at' => self::ts($k->created_at),
            'penghunian' => $k->penghunian->sortByDesc(fn (Penghunian $p) => $p->mulai_tanggal->toDateString().$p->created_at?->format('U.u'))
                ->values()->map(fn ($p) => self::penghunian($p))->all(),
            'riwayat_keanggotaan' => $k->keanggotaan->sortByDesc(fn ($m) => [$m->selesai_at === null ? 1 : 0, $m->created_at?->format('U.u')])
                ->values()->map(fn ($m) => self::keanggotaan($m))->all(),
            'akun' => $k->tautan->sortByDesc(fn ($t) => [$t->selesai_at === null ? 1 : 0, $t->created_at?->format('U.u')])
                ->values()->map(fn ($t) => self::tautan($t))->all(),
        ];
    }

    public static function penghunian(Penghunian $p): array
    {
        return [
            'id' => $p->id,
            'rumah' => $p->relationLoaded('rumah') && $p->rumah
                ? ['id' => $p->rumah->id, 'kode_rumah' => $p->rumah->kode_rumah, 'alamat' => $p->rumah->alamat()] : null,
            'keluarga_id' => $p->keluarga_id,
            'jenis_hunian' => $p->jenis_hunian,
            'mulai_tanggal' => $p->mulai_tanggal->toDateString(),
            'selesai_tanggal' => $p->selesai_tanggal?->toDateString(),
            'aktif' => $p->selesai_tanggal === null,
        ];
    }

    /** mulai_at null = tercatat sebelum M07 (tanggal tidak diketahui). */
    public static function keanggotaan(KeanggotaanKeluarga $m): array
    {
        return [
            'id' => $m->id,
            'keluarga_id' => $m->keluarga_id,
            'warga' => $m->relationLoaded('warga') && $m->warga ? ['id' => $m->warga->id, 'nama' => $m->warga->nama] : ['id' => $m->warga_id],
            'hubungan_keluarga' => $m->hubungan_keluarga,
            'mulai_at' => self::ts($m->mulai_at),
            'selesai_at' => self::ts($m->selesai_at),
            'alasan_selesai' => $m->alasan_selesai,
            'sumber' => $m->sumber,
        ];
    }

    public static function tautan(TautanAkun $t): array
    {
        return [
            'id' => $t->id,
            'akun' => $t->relationLoaded('akun') && $t->akun
                ? ['id' => $t->akun->id, 'nama' => $t->akun->nama, 'email' => $t->akun->email, 'status' => $t->akun->status] : ['id' => $t->akun_id],
            'warga' => $t->relationLoaded('warga') && $t->warga ? ['id' => $t->warga->id, 'nama' => $t->warga->nama] : ['id' => $t->warga_id],
            'keluarga_id' => $t->keluarga_id,
            'is_utama' => $t->is_utama,
            'mulai_at' => self::ts($t->mulai_at),
            'selesai_at' => self::ts($t->selesai_at),
            'alasan_selesai' => $t->alasan_selesai,
            'sumber' => $t->sumber,
        ];
    }

    public const WARGA_DETAIL = ['keanggotaanAktif.keluarga', 'keanggotaan', 'tautanAktif.akun', 'akun'];

    /** Detail anggota untuk pengurus. keluarga_id dari keanggotaan aktif (null bila sudah keluar). */
    public static function wargaDetail(Warga $w): array
    {
        $akun = $w->tautanAktif?->akun ?? $w->akun;

        return self::warga($w) + [
            'keluarga_id' => $w->keanggotaanAktif?->keluarga_id,
            'versi_keluarga' => $w->keanggotaanAktif?->keluarga?->versi,
            'archived_at' => self::ts($w->archived_at),
            'created_at' => self::ts($w->created_at),
            'akun' => $akun ? ['id' => $akun->id, 'nama' => $akun->nama, 'email' => $akun->email, 'status' => $akun->status, 'tautan_aktif' => (bool) $w->tautanAktif] : null,
            'riwayat_keanggotaan' => $w->keanggotaan->sortByDesc(fn ($m) => [$m->selesai_at === null ? 1 : 0, $m->created_at?->format('U.u')])
                ->values()->map(fn ($m) => self::keanggotaan($m))->all(),
        ];
    }

    public const AKUN_DETAIL = ['tautanAktif.keluarga.penghunianAktif.rumah', 'tautan.warga', 'permohonan.rumah'];

    /** Detail akun untuk pengurus: profil + histori tautan + permohonan. Tanpa password/token. */
    public static function akunDetail(Akun $a): array
    {
        return self::akun($a) + [
            'created_at' => self::ts($a->created_at),
            'archived_at' => self::ts($a->archived_at),
            'tautan' => $a->tautan->sortByDesc(fn ($t) => [$t->selesai_at === null ? 1 : 0, $t->created_at?->format('U.u')])
                ->values()->map(fn ($t) => self::tautan($t))->all(),
            'permohonan' => $a->permohonan ? self::permohonan($a->permohonan) : null,
        ];
    }

    public static function rumah(Rumah $r): array
    {
        return [
            'id' => $r->id,
            'kode_rumah' => $r->kode_rumah,
            'blok' => $r->blok,
            'jalan' => $r->jalan,
            'nomor' => $r->nomor,
            'alamat' => $r->alamat(),
            'tagihan_aktif' => $r->tagihan_aktif,
            'mulai_tagih' => $r->mulai_tagih?->format('Y-m'),
            'versi' => $r->versi,
            'archived_at' => self::ts($r->archived_at),
        ];
    }

    public static function permohonan(PermohonanAkun $p): array
    {
        return [
            'id' => $p->id,
            'jenis' => $p->jenis,
            'status' => $p->status,
            'nama_pemohon' => $p->nama_pemohon ?? $p->akun?->nama,
            'email' => $p->akun?->email,
            'hubungan_keluarga' => $p->hubungan_keluarga,
            'nama_kepala_diajukan' => $p->nama_kepala_diajukan,
            'alamat_diajukan' => $p->alamat_diajukan,
            'rumah_cocok' => $p->rumah ? ['id' => $p->rumah->id, 'kode_rumah' => $p->rumah->kode_rumah, 'alamat' => $p->rumah->alamat()] : null,
            'catatan' => $p->catatan,
            'diproses_at' => self::ts($p->diproses_at),
            'created_at' => self::ts($p->created_at),
        ];
    }

    /** Konfirmasi pembayaran iuran (ADR35). $admin menambah rumah dan pengirim untuk pengurus. */
    public static function konfirmasiIuran(KonfirmasiIuran $k, bool $admin): array
    {
        return [
            'id' => $k->id,
            ...(config('finance.v2') ? ['pembayaran_id' => $k->pembayaran_id] : []),
            'status' => $k->status,
            'status_label' => match ($k->status) {
                'selesai' => 'Sudah diperiksa pengurus',
                'ditolak' => 'Ditolak pengurus',
                default => 'Menunggu pemeriksaan',
            },
            'periode' => $k->periode ?? [],
            'nominal' => $k->nominal,
            'catatan' => $k->catatan,
            'catatan_pengurus' => $k->catatan_pengurus,
            'foto_url' => '/api/v1/dues/confirmations/'.$k->id.'/photo',
            'created_at' => self::ts($k->created_at),
            'diproses_at' => self::ts($k->diproses_at),
        ] + ($admin ? [
            'rumah' => $k->rumah ? ['id' => $k->rumah->id, 'kode_rumah' => $k->rumah->kode_rumah, 'alamat' => $k->rumah->alamat()] : null,
            'pengirim' => $k->akun ? ['id' => $k->akun->id, 'nama' => $k->akun->nama] : null,
        ] : []);
    }

    public static function pengaduan(Pengaduan $p, Akun $viewer): array
    {
        $isOwner = $p->pelapor_id === $viewer->id;
        $showIdentity = $viewer->isPengurus() || $isOwner || ! $p->sembunyikan_identitas;

        return [
            'id' => $p->id,
            'kategori' => $p->kategori ? ['id' => $p->kategori->id, 'nama' => $p->kategori->nama] : null,
            'judul' => $p->judul,
            'deskripsi' => $p->deskripsi,
            'status' => $p->status,
            'versi' => $p->versi,
            'sembunyikan_identitas' => $p->sembunyikan_identitas,
            'milik_saya' => $isOwner,
            'pelapor' => $showIdentity ? ['id' => $p->pelapor_id, 'nama' => $p->pelapor?->nama] : null,
            // Warga lain hanya melihat foto siap; pelapor/pengurus juga melihat diproses/gagal.
            // URL hanya untuk foto siap dan tetap lewat endpoint ber-policy (tanpa nama berkas asli).
            'foto' => $p->foto
                ->filter(fn ($f) => $f->status === 'siap' || $isOwner || $viewer->isPengurus())
                ->map(fn ($f) => [
                    'id' => $f->id, 'status' => $f->status, 'lebar' => $f->lebar, 'tinggi' => $f->tinggi,
                    'url' => $f->status === 'siap' ? '/api/v1/complaints/'.$p->id.'/photos/'.$f->id : null,
                ])->values()->all(),
            'created_at' => self::ts($p->created_at),
            'updated_at' => self::ts($p->updated_at),
            'archived_at' => $viewer->isPengurus() ? self::ts($p->archived_at) : null,
        ];
    }

    public static function aspirasi(Aspirasi $a, Akun $viewer): array
    {
        $isOwner = $a->pengirim_id === $viewer->id;
        $showIdentity = $viewer->isPengurus() || $isOwner || ! $a->sembunyikan_identitas;

        return [
            'id' => $a->id,
            'judul' => $a->judul,
            'isi' => $a->isi,
            'status' => $a->status,
            'versi' => $a->versi,
            'sembunyikan_identitas' => $a->sembunyikan_identitas,
            'milik_saya' => $isOwner,
            'pengirim' => $showIdentity ? ['id' => $a->pengirim_id, 'nama' => $a->pengirim?->nama] : null,
            'created_at' => self::ts($a->created_at),
            'updated_at' => self::ts($a->updated_at),
            'archived_at' => $viewer->isPengurus() ? self::ts($a->archived_at) : null,
        ];
    }

    /**
     * Riwayat status: aktor hanya disebut sebagai peran untuk warga, nama untuk pengurus.
     * Entri oleh pelapor anonim selalu "Warga" — termasuk bila pelapornya pengurus, agar peran
     * tidak menjadi petunjuk identitas tidak langsung.
     */
    public static function riwayat($r, Akun $viewer, bool $anonymousOwnerHidden, ?string $ownerId = null): array
    {
        $byHiddenOwner = $anonymousOwnerHidden && $ownerId !== null && $r->aktor_id === $ownerId;
        $actorIsPengurus = ! $byHiddenOwner && $r->aktor?->isPengurus();

        return [
            'status_dari' => $r->status_dari,
            'status_ke' => $r->status_ke,
            'catatan' => $r->catatan,
            'aktor' => $viewer->isPengurus()
                ? ['nama' => $r->aktor?->nama, 'peran' => $r->aktor?->peran]
                : ['nama' => $actorIsPengurus ? 'Pengurus RT' : ($anonymousOwnerHidden ? 'Warga' : $r->aktor?->nama), 'peran' => $actorIsPengurus ? 'pengurus' : 'warga'],
            'created_at' => self::ts($r->created_at),
        ];
    }

    public static function usulan(UsulanKegiatan $u, Akun $viewer): array
    {
        $own = $u->pengusul_id === $viewer->id;

        return [
            'id' => $u->id,
            'jenis' => $u->jenis,
            'nama' => $u->nama,
            'deskripsi' => $u->deskripsi,
            'lokasi' => $u->lokasi,
            'mulai_at' => self::ts($u->mulai_at),
            'selesai_at' => self::ts($u->selesai_at),
            'perkiraan_peserta' => $u->perkiraan_peserta,
            'kebutuhan' => $u->kebutuhan,
            'status' => $u->status,
            'versi' => $u->versi,
            'agenda_id' => $u->agenda_id,
            'milik_saya' => $own,
            'pengusul' => $viewer->isPengurus() || $own
                ? [
                    'id' => $u->pengusul_id,
                    'nama' => $u->relationLoaded('pengusul') ? $u->pengusul?->nama : null,
                    // Kode rumah dari snapshot pengajuan; hanya pengurus dan pengusul sendiri.
                    'rumah' => $u->rumah_id ? Rumah::whereKey($u->rumah_id)->value('kode_rumah') : null,
                ]
                : null,
            'dokumen' => $u->relationLoaded('dokumen') ? $u->dokumen->map(fn ($d) => [
                'id' => $d->id,
                'mime' => $d->mime,
                'bytes' => $d->bytes,
                'urutan' => $d->urutan,
                'url' => '/api/v1/activity-proposals/'.$u->id.'/documents/'.$d->id,
            ])->values()->all() : [],
            'created_at' => self::ts($u->created_at),
        ];
    }

    public static function agenda(Agenda $a): array
    {
        return [
            'id' => $a->id,
            'nama' => $a->nama,
            'deskripsi' => $a->deskripsi,
            'lokasi' => $a->lokasi,
            'mulai_at' => self::ts($a->mulai_at),
            'selesai_at' => self::ts($a->selesai_at),
            'google_calendar_url' => Calendar::googleUrl($a),
            'ics_url' => '/api/v1/agendas/'.$a->id.'/calendar',
            'archived_at' => self::ts($a->archived_at),
        ];
    }

    public static function pengumuman(Pengumuman $p): array
    {
        return [
            'id' => $p->id,
            'judul' => $p->judul,
            'isi' => $p->isi,
            'published_at' => self::ts($p->published_at),
            'archived_at' => self::ts($p->archived_at),
        ];
    }

    public static function notifikasi(Notifikasi $n): array
    {
        $ref = collect(['pengumuman_id', 'agenda_id', 'pengaduan_id', 'aspirasi_id', 'tagihan_id', 'usulan_kegiatan_id'])
            ->first(fn ($k) => $n->{$k} !== null);

        return [
            'id' => $n->id,
            'jenis' => $n->jenis,
            'judul' => $n->judul,
            'isi' => $n->isi,
            'referensi' => $ref ? ['tipe' => str_replace('_id', '', $ref), 'id' => $n->{$ref}] : null,
            'dibaca_at' => self::ts($n->dibaca_at),
            'created_at' => self::ts($n->created_at),
        ];
    }

    public static function tagihan(TagihanIuran $t): array
    {
        $active = $t->relationLoaded('alokasi') ? $t->alokasi->firstWhere('dibatalkan_at', null) : null;
        if (config('finance.v2')) {
            $s = $t->saldo;
            $status = (int) $s->sisa === 0 ? ((int) $s->keringanan > 0 ? 'lunas_keringanan' : 'lunas')
                : ((int) $s->terbayar_uang + (int) $s->terbayar_titipan + (int) $s->keringanan > 0 ? 'kurang_bayar' : 'belum_bayar');

            return ['id' => $t->id, 'periode' => $t->periode->format('Y-m'), 'nominal' => (int) $s->nominal_efektif,
                'nominal_snapshot' => (int) $s->nominal_tagihan, 'nominal_efektif' => (int) $s->nominal_efektif,
                'terbayar' => (int) $s->terbayar_uang + (int) $s->terbayar_titipan, 'terbayar_titipan' => (int) $s->terbayar_titipan,
                'keringanan' => (int) $s->keringanan, 'sisa' => (int) $s->sisa, 'jatuh_tempo' => $t->jatuh_tempo->toDateString(),
                'status' => $t->status, 'status_pelunasan' => $status,
                'status_label' => ['lunas' => 'Lunas', 'lunas_keringanan' => 'Lunas dengan keringanan', 'kurang_bayar' => 'Kurang bayar', 'belum_bayar' => 'Belum dibayar'][$status],
                'pembayaran_id' => $active?->pembayaran_id,
                'alokasi' => $t->alokasi->map(fn ($a) => ['pembayaran_id' => $a->pembayaran_id, 'penggunaan_titipan_id' => $a->penggunaan_titipan_id,
                    'nominal' => $a->nominal_alokasi, 'dibatalkan_at' => self::ts($a->dibatalkan_at)])->values()->all()];
        }

        return [
            'id' => $t->id,
            'periode' => $t->periode->format('Y-m'),
            'nominal' => $t->nominal_tagihan,
            'jatuh_tempo' => $t->jatuh_tempo->toDateString(),
            'status' => $t->status,
            'status_label' => $t->status === 'lunas' ? 'Lunas' : 'Belum dibayar',
            'pembayaran_id' => $active?->pembayaran_id,
        ];
    }

    public static function pembayaran(PembayaranIuran $p): array
    {
        $periods = $p->alokasi->map(fn ($a) => $a->tagihan?->periode->format('Y-m'))->filter();
        if (config('finance.v2')) {
            $periods = $periods->merge($p->keringanan->map(fn ($k) => $k->tagihan?->periode->format('Y-m'))->filter());
        }

        return [
            ...(config('finance.v2') ? ['nominal_sumbangan' => (int) $p->nominal_sumbangan, 'nominal_titipan' => (int) $p->nominal_titipan,
                'nominal_alokasi' => (int) $p->alokasi->sum('nominal_alokasi'), 'versi_keuangan' => (int) $p->versi_keuangan] : []),
            'id' => $p->id,
            'nomor_bukti' => $p->nomor_bukti,
            'rumah' => $p->rumah ? ['id' => $p->rumah->id, 'kode_rumah' => $p->rumah->kode_rumah] : null,
            'tanggal_bayar' => $p->tanggal_bayar->toDateString(),
            'total_bayar' => $p->total_bayar,
            'status' => $p->status,
            'catatan' => $p->catatan,
            'periode' => $periods->unique()->sort()->values()->all(),
            'menggantikan_id' => $p->menggantikan_id,
            'alasan_pembatalan' => $p->alasan_pembatalan,
            'dibatalkan_at' => self::ts($p->dibatalkan_at),
            'created_at' => self::ts($p->created_at),
        ];
    }

    /** Kas hanya untuk pengurus (ADR26), jadi selalu detail lengkap. */
    public static function kas(TransaksiKas $k, bool $reversed = false): array
    {
        return array_filter([
            // Hanya kas manual non-iuran yang belum dibalik; kas iuran dibatalkan lewat pembayaran.
            'dapat_dibalik' => $k->sumber !== 'iuran' && $k->jenis !== 'pembalikan' && ! $reversed,
            'id' => $k->id,
            'jenis' => $k->jenis,
            'sumber' => $k->sumber,
            'kategori' => $k->kategori,
            'nominal' => $k->nominal,
            'tanggal' => $k->tanggal->toDateString(),
            'keterangan' => $k->keterangan,
            'membalik_id' => $k->membalik_id,
            'pembayaran_id' => $k->pembayaran_id,
            'created_at' => self::ts($k->created_at),
        ], fn ($v) => $v !== null);
    }
}
