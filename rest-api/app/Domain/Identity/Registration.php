<?php

namespace App\Domain\Identity;

use App\Domain\Residents\Address;
use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\Keluarga;
use App\Models\Penghunian;
use App\Models\PermohonanAkun;
use App\Models\PreferensiNotifikasi;
use App\Models\Rumah;
use App\Models\Warga;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Registrasi (FR03/FR05, BR03/BR05/BR06). Satu transaksi: akun + rumah + keluarga + warga + penghunian.
 * Rumah yang sudah punya keluarga aktif tidak di-merge otomatis; pemohon diarahkan ke akun tambahan.
 */
final class Registration
{
    /**
     * @param  array{email:string,password:string,nama_kepala:string,whatsapp:?string,blok:?string,jalan:string,nomor:string,jenis_hunian:string,anggota:array<int,array{nama:string,hubungan_keluarga:string}>}  $data
     */
    public static function primary(array $data, string $tokenCode): Akun
    {
        return DB::transaction(function () use ($data, $tokenCode) {
            $token = RegistrationToken::verify($tokenCode);
            if (! $token) {
                throw new DomainConflict('REGISTRATION_TOKEN_INVALID', 'Data pendaftaran tidak dapat diproses. Periksa kembali token RT.', 422);
            }

            $email = strtolower(trim($data['email']));
            if (Akun::where('email', $email)->exists()) {
                // Pesan generik agar tidak membuka daftar email warga.
                throw new DomainConflict('REGISTRATION_REJECTED', 'Pendaftaran tidak dapat diproses. Hubungi pengurus bila sudah pernah mendaftar.', 422);
            }

            $key = Address::key($data['blok'] ?? null, $data['jalan'], $data['nomor']);
            $rumah = Rumah::where('alamat_key', $key)->lockForUpdate()->first();
            if ($rumah && $rumah->penghunianAktif()->exists()) {
                throw new DomainConflict('HOUSE_ALREADY_REGISTERED', 'Rumah ini sudah terdaftar. Daftar sebagai akun tambahan dan tunggu persetujuan pengurus.');
            }

            if (! $rumah) {
                $code = Address::code($data['blok'] ?? null, $data['nomor']);
                $suffix = 1;
                $base = $code;
                while (Rumah::where('kode_rumah', $code)->exists()) {
                    $code = $base.'-'.(++$suffix);
                }
                $rumah = Rumah::create([
                    'kode_rumah' => $code,
                    'blok' => $data['blok'] ?? null,
                    'jalan' => trim($data['jalan']),
                    'nomor' => trim($data['nomor']),
                    'alamat_key' => $key,
                    'tagihan_aktif' => true,
                    'mulai_tagih' => now('Asia/Jakarta')->startOfMonth()->toDateString(),
                ]);
            }

            $keluarga = Keluarga::create(['status' => 'belum_verifikasi']);
            $kepala = Warga::create([
                'keluarga_id' => $keluarga->id,
                'nama' => trim($data['nama_kepala']),
                'hubungan_keluarga' => 'Kepala keluarga',
                'is_kepala' => true,
                'whatsapp' => $data['whatsapp'] ?? null,
            ]);
            foreach ($data['anggota'] ?? [] as $anggota) {
                Warga::create([
                    'keluarga_id' => $keluarga->id,
                    'nama' => trim($anggota['nama']),
                    'hubungan_keluarga' => trim($anggota['hubungan_keluarga']),
                    'is_kepala' => false,
                ]);
            }
            Penghunian::create([
                'rumah_id' => $rumah->id,
                'keluarga_id' => $keluarga->id,
                'jenis_hunian' => $data['jenis_hunian'],
                'mulai_tanggal' => now('Asia/Jakarta')->toDateString(),
            ]);

            $akun = Akun::create([
                'warga_id' => $kepala->id,
                'nama' => $kepala->nama,
                'email' => $email,
                'password_hash' => $data['password'],
                'jenis' => 'utama',
                'status' => 'aktif',
            ]);
            PermohonanAkun::create([
                'akun_id' => $akun->id,
                'token_id' => $token->id,
                'jenis' => 'utama',
                'status' => 'disetujui',
                'rumah_id' => $rumah->id,
                'diproses_at' => now(),
                'catatan' => 'Akun utama aktif otomatis; data keluarga menunggu verifikasi pengurus.',
            ]);
            PreferensiNotifikasi::create(['akun_id' => $akun->id]);

            Audit::record($akun, 'akun.registrasi_utama', 'akun', $akun->id, ['rumah_id' => $rumah->id, 'anggota' => count($data['anggota'] ?? [])]);
            Outbox::record('akun.registrasi_utama', 'akun', $akun->id);

            return $akun;
        });
    }

    /** Akun tambahan: status menunggu sampai pengurus menyetujui; rumah dicocokkan diam-diam. */
    public static function additional(array $data, string $tokenCode): Akun
    {
        return DB::transaction(function () use ($data, $tokenCode) {
            $token = RegistrationToken::verify($tokenCode);
            if (! $token) {
                throw new DomainConflict('REGISTRATION_TOKEN_INVALID', 'Data pendaftaran tidak dapat diproses. Periksa kembali token RT.', 422);
            }
            $email = strtolower(trim($data['email']));
            if (Akun::where('email', $email)->exists()) {
                throw new DomainConflict('REGISTRATION_REJECTED', 'Pendaftaran tidak dapat diproses. Hubungi pengurus bila sudah pernah mendaftar.', 422);
            }

            $rumah = Rumah::where('alamat_key', Address::key($data['blok'] ?? null, $data['jalan'], $data['nomor']))->first();

            $akun = Akun::create([
                'nama' => trim($data['nama']),
                'email' => $email,
                'password_hash' => $data['password'],
                'jenis' => 'tambahan',
                'status' => 'menunggu',
            ]);
            PermohonanAkun::create([
                'akun_id' => $akun->id,
                'token_id' => $token->id,
                'jenis' => 'tambahan',
                'status' => 'diajukan',
                'rumah_id' => $rumah?->id,
                'nama_pemohon' => trim($data['nama']),
                'hubungan_keluarga' => trim($data['hubungan_keluarga']),
                'alamat_diajukan' => trim(($data['blok'] ?? '').' '.$data['jalan'].' '.$data['nomor']),
            ]);
            PreferensiNotifikasi::create(['akun_id' => $akun->id]);

            Audit::record($akun, 'akun.registrasi_tambahan', 'akun', $akun->id, ['rumah_cocok' => (bool) $rumah]);
            Outbox::record('akun.permohonan_tambahan', 'akun', $akun->id);

            return $akun;
        });
    }

    /** Persetujuan pengurus: buat warga di keluarga rumah tersebut dan aktifkan akun (peran tetap warga). */
    public static function approve(PermohonanAkun $permohonan, Akun $pengurus, ?string $rumahId, ?string $catatan): void
    {
        DB::transaction(function () use ($permohonan, $pengurus, $rumahId, $catatan) {
            $permohonan = PermohonanAkun::whereKey($permohonan->id)->lockForUpdate()->firstOrFail();
            if ($permohonan->status !== 'diajukan' || $permohonan->jenis !== 'tambahan') {
                throw new DomainConflict('REQUEST_ALREADY_PROCESSED', 'Permohonan sudah diproses.');
            }
            $rumah = Rumah::find($rumahId ?? $permohonan->rumah_id);
            $penghunian = $rumah?->penghunianAktif;
            if (! $penghunian) {
                throw new DomainConflict('HOUSE_NOT_OCCUPIED', 'Pilih rumah yang memiliki keluarga aktif.', 422);
            }

            $warga = Warga::create([
                'keluarga_id' => $penghunian->keluarga_id,
                'nama' => $permohonan->nama_pemohon,
                'hubungan_keluarga' => $permohonan->hubungan_keluarga ?? 'Anggota keluarga',
            ]);
            $akun = Akun::whereKey($permohonan->akun_id)->lockForUpdate()->firstOrFail();
            $akun->forceFill(['warga_id' => $warga->id, 'status' => 'aktif'])->save();
            $permohonan->forceFill([
                'status' => 'disetujui',
                'rumah_id' => $rumah->id,
                'diproses_oleh_id' => $pengurus->id,
                'diproses_at' => now(),
                'catatan' => $catatan,
            ])->save();

            Audit::record($pengurus, 'permohonan_akun.disetujui', 'permohonan_akun', $permohonan->id, ['akun_id' => $akun->id]);
            Outbox::record('akun.permohonan_disetujui', 'akun', $akun->id);
        });
    }

    public static function reject(PermohonanAkun $permohonan, Akun $pengurus, string $alasan): void
    {
        DB::transaction(function () use ($permohonan, $pengurus, $alasan) {
            $permohonan = PermohonanAkun::whereKey($permohonan->id)->lockForUpdate()->firstOrFail();
            if ($permohonan->status !== 'diajukan') {
                throw new DomainConflict('REQUEST_ALREADY_PROCESSED', 'Permohonan sudah diproses.');
            }
            $permohonan->forceFill(['status' => 'ditolak', 'diproses_oleh_id' => $pengurus->id, 'diproses_at' => now(), 'catatan' => $alasan])->save();
            Akun::whereKey($permohonan->akun_id)->update(['status' => 'nonaktif']);
            Akun::find($permohonan->akun_id)?->tokens()->delete();

            Audit::record($pengurus, 'permohonan_akun.ditolak', 'permohonan_akun', $permohonan->id, ['alasan' => $alasan]);
        });
    }
}
