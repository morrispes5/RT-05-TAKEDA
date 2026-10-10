<?php

namespace App\Http\Responses;

use App\Domain\Community\Calendar;
use App\Models\Agenda;
use App\Models\Akun;
use App\Models\Aspirasi;
use App\Models\Keluarga;
use App\Models\Notifikasi;
use App\Models\PembayaranIuran;
use App\Models\Pengaduan;
use App\Models\Pengumuman;
use App\Models\PermohonanAkun;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Models\TransaksiKas;
use App\Models\Warga;
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
        return $t?->format('Y-m-d\TH:i:s\Z');
    }

    public static function akun(Akun $a): array
    {
        $rumah = $a->rumah();

        return [
            'id' => $a->id,
            'nama' => $a->nama,
            'email' => $a->email,
            'peran' => $a->peran,
            'jenis' => $a->jenis,
            'status' => $a->status,
            'rumah' => $rumah ? ['id' => $rumah->id, 'kode_rumah' => $rumah->kode_rumah, 'alamat' => $rumah->alamat()] : null,
            'keluarga_id' => $a->warga?->keluarga_id,
            'dapat_ubah_keluarga' => $a->isAktif() && ($a->isUtama() || $a->isPengurus()),
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
            'status' => $w->status,
            'punya_akun' => $w->relationLoaded('akun') ? (bool) $w->akun : null,
        ];
    }

    public static function keluarga(Keluarga $k, ?Rumah $rumah): array
    {
        return [
            'id' => $k->id,
            'status' => $k->status,
            'versi' => $k->versi,
            'rumah' => $rumah ? self::rumah($rumah) : null,
            'jenis_hunian' => $k->penghunianAktif?->jenis_hunian,
            'anggota' => $k->warga->whereNull('archived_at')->sortByDesc('is_kepala')->values()->map(fn ($w) => self::warga($w))->all(),
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
            'alamat_diajukan' => $p->alamat_diajukan,
            'rumah_cocok' => $p->rumah ? ['id' => $p->rumah->id, 'kode_rumah' => $p->rumah->kode_rumah, 'alamat' => $p->rumah->alamat()] : null,
            'catatan' => $p->catatan,
            'diproses_at' => self::ts($p->diproses_at),
            'created_at' => self::ts($p->created_at),
        ];
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
            'foto' => $p->foto->map(fn ($f) => [
                'id' => $f->id, 'lebar' => $f->lebar, 'tinggi' => $f->tinggi,
                'url' => '/api/v1/complaints/'.$p->id.'/photos/'.$f->id,
            ])->all(),
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
        ];
    }

    /** Riwayat status: aktor hanya disebut sebagai peran untuk warga, nama untuk pengurus. */
    public static function riwayat($r, Akun $viewer, bool $anonymousOwnerHidden): array
    {
        $actorIsPengurus = $r->aktor?->isPengurus();

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
        $ref = collect(['pengumuman_id', 'agenda_id', 'pengaduan_id', 'aspirasi_id', 'tagihan_id'])
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
        return [
            'id' => $p->id,
            'nomor_bukti' => $p->nomor_bukti,
            'rumah' => $p->rumah ? ['id' => $p->rumah->id, 'kode_rumah' => $p->rumah->kode_rumah] : null,
            'tanggal_bayar' => $p->tanggal_bayar->toDateString(),
            'total_bayar' => $p->total_bayar,
            'status' => $p->status,
            'catatan' => $p->catatan,
            'periode' => $p->alokasi->map(fn ($a) => $a->tagihan?->periode->format('Y-m'))->filter()->values()->all(),
            'menggantikan_id' => $p->menggantikan_id,
            'alasan_pembatalan' => $p->alasan_pembatalan,
            'dibatalkan_at' => self::ts($p->dibatalkan_at),
            'created_at' => self::ts($p->created_at),
        ];
    }

    public static function kas(TransaksiKas $k, bool $detail): array
    {
        return array_filter([
            'id' => $k->id,
            'jenis' => $k->jenis,
            'sumber' => $k->sumber,
            'kategori' => $k->kategori,
            'nominal' => $k->nominal,
            'tanggal' => $k->tanggal->toDateString(),
            // Warga melihat keterangan umum; detail (nomor bukti/rumah) hanya untuk pengurus.
            'keterangan' => $detail ? $k->keterangan : ($k->sumber === 'iuran' ? 'Iuran warga' : $k->keterangan),
            'membalik_id' => $detail ? $k->membalik_id : null,
            'pembayaran_id' => $detail ? $k->pembayaran_id : null,
            'created_at' => self::ts($k->created_at),
        ], fn ($v) => $v !== null);
    }
}
