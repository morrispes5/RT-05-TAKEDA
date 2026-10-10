<?php

namespace App\Domain\Services;

use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\Aspirasi;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use App\Models\Pengaduan;
use App\Models\RiwayatAspirasi;
use App\Models\RiwayatPengaduan;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pengaduan dan aspirasi (FR07–FR09, BR08/BR09) — dua domain terpisah, pola yang sama.
 * Status + baris riwayat ditulis dalam satu transaksi; perubahan basi (versi beda) → 409.
 * Admin boleh membuka ulang selesai → diproses/ditinjau dengan catatan wajib.
 */
final class Reports
{
    public const COMPLAINT_FLOW = ['diajukan' => ['diproses'], 'diproses' => ['selesai'], 'selesai' => ['diproses']];

    public const ASPIRATION_FLOW = ['diajukan' => ['ditinjau'], 'ditinjau' => ['selesai'], 'selesai' => ['ditinjau']];

    /** @param list<UploadedFile> $photos */
    public static function createComplaint(Akun $akun, array $data, array $photos): Pengaduan
    {
        if (count($photos) > 5) {
            throw new DomainConflict('TOO_MANY_PHOTOS', 'Maksimal 5 foto.', 422);
        }
        $kategori = KategoriPengaduan::whereKey($data['kategori_id'])->where('aktif', true)->first();
        if (! $kategori) {
            throw new DomainConflict('CATEGORY_INACTIVE', 'Kategori tidak tersedia.', 422);
        }

        // Foto diproses sebelum transaksi; bila transaksi gagal, berkas yatim dihapus.
        $stored = array_map(fn (UploadedFile $f) => Photo::store($f, 'pengaduan'), $photos);

        try {
            return DB::transaction(function () use ($akun, $data, $stored, $kategori) {
                $pengaduan = Pengaduan::create([
                    'pelapor_id' => $akun->id,
                    'kategori_id' => $kategori->id,
                    'rumah_id' => $akun->rumah()?->id,
                    'judul' => trim($data['judul']),
                    'deskripsi' => trim($data['deskripsi']),
                    'sembunyikan_identitas' => (bool) ($data['sembunyikan_identitas'] ?? false),
                    'status' => 'diajukan',
                ]);
                foreach ($stored as $i => $meta) {
                    FotoPengaduan::create($meta + ['pengaduan_id' => $pengaduan->id, 'urutan' => $i + 1]);
                }
                RiwayatPengaduan::create(['pengaduan_id' => $pengaduan->id, 'aktor_id' => $akun->id, 'status_ke' => 'diajukan']);
                Audit::record($akun, 'pengaduan.dibuat', 'pengaduan', $pengaduan->id, ['foto' => count($stored)]);
                Outbox::record('pengaduan.dibuat', 'pengaduan', $pengaduan->id);

                return $pengaduan;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $meta) {
                Storage::disk(Photo::DISK)->delete($meta['storage_key']);
            }
            throw $e;
        }
    }

    public static function updateOwnComplaint(Akun $akun, Pengaduan $pengaduan, array $data): Pengaduan
    {
        return DB::transaction(function () use ($akun, $pengaduan, $data) {
            $locked = Pengaduan::whereKey($pengaduan->id)->lockForUpdate()->firstOrFail();
            if ($locked->pelapor_id !== $akun->id || $locked->status !== 'diajukan' || $locked->archived_at) {
                throw new DomainConflict('COMPLAINT_LOCKED', 'Pengaduan hanya dapat diubah pemiliknya selama masih diajukan.', 403);
            }
            self::assertVersion($locked->versi, $data['versi'] ?? null);
            $locked->fill(array_intersect_key($data, array_flip(['judul', 'deskripsi', 'sembunyikan_identitas'])));
            $locked->versi++;
            $locked->save();
            Audit::record($akun, 'pengaduan.diubah_pemilik', 'pengaduan', $locked->id);

            return $locked;
        });
    }

    public static function changeComplaintStatus(Akun $pengurus, Pengaduan $pengaduan, string $to, ?string $note, int $expectedVersion): Pengaduan
    {
        return DB::transaction(function () use ($pengurus, $pengaduan, $to, $note, $expectedVersion) {
            $locked = Pengaduan::whereKey($pengaduan->id)->lockForUpdate()->firstOrFail();
            self::assertVersion($locked->versi, $expectedVersion);
            self::assertTransition(self::COMPLAINT_FLOW, $locked->status, $to, $note);
            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'versi' => $locked->versi + 1])->save();
            RiwayatPengaduan::create(['pengaduan_id' => $locked->id, 'aktor_id' => $pengurus->id, 'status_dari' => $from, 'status_ke' => $to, 'catatan' => $note]);
            Audit::record($pengurus, 'pengaduan.status', 'pengaduan', $locked->id, ['dari' => $from, 'ke' => $to]);
            Outbox::record('pengaduan.status_berubah', 'pengaduan', $locked->id, ['status' => $to]);

            return $locked;
        });
    }

    public static function createAspiration(Akun $akun, array $data): Aspirasi
    {
        return DB::transaction(function () use ($akun, $data) {
            $aspirasi = Aspirasi::create([
                'pengirim_id' => $akun->id,
                'judul' => trim($data['judul']),
                'isi' => trim($data['isi']),
                'sembunyikan_identitas' => (bool) ($data['sembunyikan_identitas'] ?? false),
                'status' => 'diajukan',
            ]);
            RiwayatAspirasi::create(['aspirasi_id' => $aspirasi->id, 'aktor_id' => $akun->id, 'status_ke' => 'diajukan']);
            Audit::record($akun, 'aspirasi.dibuat', 'aspirasi', $aspirasi->id);
            Outbox::record('aspirasi.dibuat', 'aspirasi', $aspirasi->id);

            return $aspirasi;
        });
    }

    public static function updateOwnAspiration(Akun $akun, Aspirasi $aspirasi, array $data): Aspirasi
    {
        return DB::transaction(function () use ($akun, $aspirasi, $data) {
            $locked = Aspirasi::whereKey($aspirasi->id)->lockForUpdate()->firstOrFail();
            if ($locked->pengirim_id !== $akun->id || $locked->status !== 'diajukan' || $locked->archived_at) {
                throw new DomainConflict('ASPIRATION_LOCKED', 'Aspirasi hanya dapat diubah pengirimnya selama masih diajukan.', 403);
            }
            self::assertVersion($locked->versi, $data['versi'] ?? null);
            $locked->fill(array_intersect_key($data, array_flip(['judul', 'isi', 'sembunyikan_identitas'])));
            $locked->versi++;
            $locked->save();
            Audit::record($akun, 'aspirasi.diubah_pemilik', 'aspirasi', $locked->id);

            return $locked;
        });
    }

    public static function changeAspirationStatus(Akun $pengurus, Aspirasi $aspirasi, string $to, ?string $note, int $expectedVersion): Aspirasi
    {
        return DB::transaction(function () use ($pengurus, $aspirasi, $to, $note, $expectedVersion) {
            $locked = Aspirasi::whereKey($aspirasi->id)->lockForUpdate()->firstOrFail();
            self::assertVersion($locked->versi, $expectedVersion);
            self::assertTransition(self::ASPIRATION_FLOW, $locked->status, $to, $note);
            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'versi' => $locked->versi + 1])->save();
            RiwayatAspirasi::create(['aspirasi_id' => $locked->id, 'aktor_id' => $pengurus->id, 'status_dari' => $from, 'status_ke' => $to, 'catatan' => $note]);
            Audit::record($pengurus, 'aspirasi.status', 'aspirasi', $locked->id, ['dari' => $from, 'ke' => $to]);
            Outbox::record('aspirasi.status_berubah', 'aspirasi', $locked->id, ['status' => $to]);

            return $locked;
        });
    }

    public static function archive(Akun $pengurus, Pengaduan|Aspirasi $report, string $reason): void
    {
        DB::transaction(function () use ($pengurus, $report, $reason) {
            $report->forceFill(['archived_at' => now()])->save();
            Audit::record($pengurus, $report->getTable().'.diarsipkan', $report->getTable(), $report->id, ['alasan' => $reason]);
        });
    }

    private static function assertVersion(int $current, mixed $expected): void
    {
        if ($expected === null || (int) $expected !== $current) {
            throw new DomainConflict('VERSION_CONFLICT', 'Data sudah berubah. Muat ulang lalu coba lagi.');
        }
    }

    private static function assertTransition(array $flow, string $from, string $to, ?string $note): void
    {
        if (! in_array($to, $flow[$from] ?? [], true)) {
            throw new DomainConflict('INVALID_STATUS_TRANSITION', "Status tidak dapat diubah dari $from ke $to.", 422);
        }
        $reopen = ($from === 'selesai');
        if ($reopen && trim((string) $note) === '') {
            throw new DomainConflict('REOPEN_REASON_REQUIRED', 'Alasan wajib diisi untuk membuka ulang laporan.', 422);
        }
    }
}
