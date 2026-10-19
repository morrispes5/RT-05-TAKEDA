<?php

namespace App\Domain\Services;

use App\Exceptions\DomainConflict;
use App\Jobs\ProcessComplaintPhoto;
use App\Models\Akun;
use App\Models\Aspirasi;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use App\Models\Pengaduan;
use App\Models\RiwayatAspirasi;
use App\Models\RiwayatPengaduan;
use App\Support\Audit;
use App\Support\Outbox;
use App\Support\SharedHosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pengaduan dan aspirasi (FR07–FR09, BR08/BR09) — dua domain terpisah, pola yang sama.
 * Status + baris riwayat ditulis dalam satu transaksi; perubahan basi (versi beda) → 409.
 * Admin boleh membuka ulang selesai → diproses/ditinjau dengan catatan wajib.
 */
final class Reports
{
    public const COMPLAINT_FLOW = ['diajukan' => ['diproses'], 'diproses' => ['selesai'], 'selesai' => ['diproses']];

    public const ASPIRATION_FLOW = ['diajukan' => ['ditinjau'], 'ditinjau' => ['selesai'], 'selesai' => ['ditinjau']];

    /** Batas per akun per hari (WIB), semua profil sejak UAT M15: cegah spam unggahan yang mengisi penyimpanan. */
    public const DAILY_REPORTS = 5;

    public const DAILY_PHOTOS = 25;

    public const MAX_PHOTOS = 5;

    /** Nama lama (profil shared, ADR23) tetap dipakai tes. */
    public const SHARED_DAILY_REPORTS = self::DAILY_REPORTS;

    public const SHARED_DAILY_PHOTOS = self::DAILY_PHOTOS;

    /** @param list<UploadedFile> $photos */
    public static function createComplaint(Akun $akun, array $data, array $photos): Pengaduan
    {
        if (count($photos) > self::MAX_PHOTOS) {
            throw new DomainConflict('TOO_MANY_PHOTOS', 'Maksimal 5 foto.', 422);
        }
        $kategori = KategoriPengaduan::whereKey($data['kategori_id'])->where('aktif', true)->first();
        if (! $kategori) {
            throw new DomainConflict('CATEGORY_INACTIVE', 'Kategori tidak tersedia.', 422);
        }
        self::assertDailyQuota($akun, count($photos), newReport: true);

        // Foto divalidasi lalu disalin mentah ke folder privat sebelum transaksi; transkode dan
        // penghapusan metadata dilakukan setelah response (shared) atau worker-media (VPS).
        // Bila transaksi gagal, berkas yatim dihapus.
        $stored = [];
        try {
            foreach ($photos as $file) {
                $stored[] = Photo::stage($file);
            }
            $pengaduan = DB::transaction(function () use ($akun, $data, $stored, $kategori) {
                // Kunci akun: dua unggahan paralel tidak bisa sama-sama lolos batas harian.
                Akun::whereKey($akun->id)->lockForUpdate()->first();
                self::assertDailyQuota($akun, count($stored), newReport: true);
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
        } catch (Throwable $e) {
            foreach ($stored as $meta) {
                Storage::disk(Photo::DISK)->delete($meta['storage_key']);
            }
            throw $e;
        }

        // Setelah commit. Antrean gagal tidak membatalkan laporan; rt05:media-recover mengirim ulang.
        foreach ($pengaduan->foto()->pluck('id') as $fotoId) {
            self::enqueuePhoto($fotoId);
        }

        // Muat ulang default kolom (versi, created_at) agar respons create sama dengan detail.
        return $pengaduan->refresh();
    }

    public static function enqueuePhoto(string $fotoId): void
    {
        try {
            if (SharedHosting::enabled()) {
                // Respons create tetap status diproses. Transkode jalan di proses yang sama
                // setelah response terkirim. Bila proses mati, rt05:media-recover mengirim ulang.
                SharedHosting::afterResponse(function () use ($fotoId) {
                    (new ProcessComplaintPhoto($fotoId))->handle();
                });

                return;
            }
            ProcessComplaintPhoto::dispatch($fotoId);
        } catch (Throwable $e) {
            Log::warning('media.enqueue_failed', ['foto_id' => $fotoId, 'exception' => $e::class]);
        }
    }

    /** Batas harian per akun (pengaduan baru dan total foto yang diunggah hari ini, termasuk foto tambahan). */
    public static function assertDailyQuota(Akun $akun, int $newPhotos, bool $newReport = false): void
    {
        $start = now('Asia/Jakarta')->startOfDay()->utc();
        if ($newReport) {
            $reports = Pengaduan::where('pelapor_id', $akun->id)->where('created_at', '>=', $start)->count();
            if ($reports >= self::DAILY_REPORTS) {
                throw new DomainConflict('DAILY_QUOTA', 'Maksimal 5 pengaduan per hari. Coba lagi besok.', 429);
            }
        }
        $photos = FotoPengaduan::whereHas('pengaduan', fn ($q) => $q->where('pelapor_id', $akun->id))
            ->where('created_at', '>=', $start)
            ->count();
        if ($photos + $newPhotos > self::DAILY_PHOTOS) {
            throw new DomainConflict('DAILY_QUOTA', 'Maksimal 25 foto per hari. Coba lagi besok.', 429);
        }
    }

    /**
     * UAT M15: pemilik menambah foto selama pengaduan masih diajukan (total maks. 5, batas harian
     * tetap berlaku). Versi naik; foto baru diproses worker-media seperti saat pengaduan dibuat.
     *
     * @param  list<UploadedFile>  $photos
     */
    public static function addComplaintPhotos(Akun $akun, Pengaduan $pengaduan, int $versi, array $photos): Pengaduan
    {
        if ($photos === []) {
            throw new DomainConflict('VALIDATION_FAILED', 'Pilih setidaknya satu foto.', 422);
        }
        $stored = [];
        $created = [];
        try {
            foreach ($photos as $file) {
                $stored[] = Photo::stage($file);
            }
            $locked = DB::transaction(function () use ($akun, $pengaduan, $versi, $stored, &$created) {
                Akun::whereKey($akun->id)->lockForUpdate()->first();
                $locked = self::lockOwnSubmitted($akun, $pengaduan, $versi);
                $existing = FotoPengaduan::where('pengaduan_id', $locked->id)->count();
                if ($existing + count($stored) > self::MAX_PHOTOS) {
                    throw new DomainConflict('TOO_MANY_PHOTOS', 'Maksimal 5 foto per pengaduan. Hapus foto lain dulu.', 422);
                }
                self::assertDailyQuota($akun, count($stored));
                $next = (int) FotoPengaduan::where('pengaduan_id', $locked->id)->max('urutan');
                foreach ($stored as $i => $meta) {
                    $created[] = FotoPengaduan::create($meta + ['pengaduan_id' => $locked->id, 'urutan' => $next + $i + 1])->id;
                }
                $locked->versi++;
                $locked->save();
                Audit::record($akun, 'pengaduan.foto_ditambah', 'pengaduan', $locked->id, ['foto' => count($stored)]);

                return $locked;
            });
        } catch (Throwable $e) {
            foreach ($stored as $meta) {
                Storage::disk(Photo::DISK)->delete($meta['storage_key']);
            }
            throw $e;
        }
        foreach ($created as $fotoId) {
            self::enqueuePhoto($fotoId);
        }

        return $locked->refresh();
    }

    /** UAT M15: pemilik menghapus satu foto selama pengaduan masih diajukan. Berkas ikut dihapus setelah commit. */
    public static function removeComplaintPhoto(Akun $akun, Pengaduan $pengaduan, string $fotoId, int $versi): Pengaduan
    {
        $key = null;
        $locked = DB::transaction(function () use ($akun, $pengaduan, $fotoId, $versi, &$key) {
            $locked = self::lockOwnSubmitted($akun, $pengaduan, $versi);
            $foto = FotoPengaduan::where('pengaduan_id', $locked->id)->lockForUpdate()->findOrFail($fotoId);
            $key = $foto->storage_key;
            $foto->delete();
            $locked->versi++;
            $locked->save();
            Audit::record($akun, 'pengaduan.foto_dihapus', 'pengaduan', $locked->id);

            return $locked;
        });
        try {
            Storage::disk(Photo::DISK)->delete($key);
        } catch (Throwable $e) {
            // Berkas yatim dibersihkan rt05:media-recover; penghapusan baris sudah commit.
            Log::warning('media.delete_failed', ['exception' => $e::class]);
        }

        return $locked->refresh();
    }

    private static function lockOwnSubmitted(Akun $akun, Pengaduan $pengaduan, mixed $versi): Pengaduan
    {
        $locked = Pengaduan::whereKey($pengaduan->id)->lockForUpdate()->firstOrFail();
        if ($locked->pelapor_id !== $akun->id || $locked->status !== 'diajukan' || $locked->archived_at) {
            throw new DomainConflict('COMPLAINT_LOCKED', 'Pengaduan hanya dapat diubah pemiliknya selama masih diajukan.', 403);
        }
        self::assertVersion($locked->versi, $versi);

        return $locked;
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
            self::assertNotArchived($locked);
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

            return $aspirasi->refresh();
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
            self::assertNotArchived($locked);
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
            $locked = $report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            self::assertNotArchived($locked);
            // Arsip hanya menyembunyikan dari warga; laporan, foto, dan riwayat tetap utuh.
            $locked->forceFill(['archived_at' => now()])->save();
            Audit::record($pengurus, $locked->getTable().'.diarsipkan', $locked->getTable(), $locked->id, ['alasan' => $reason]);
        });
    }

    private static function assertNotArchived(Pengaduan|Aspirasi $report): void
    {
        if ($report->archived_at) {
            throw new DomainConflict('ALREADY_ARCHIVED', 'Laporan sudah diarsipkan.');
        }
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
