<?php

namespace App\Jobs;

use App\Domain\Services\Photo;
use App\Models\FotoPengaduan;
use App\Support\SharedHosting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * worker-media (antrean media): transkode foto pengaduan diproses → siap | gagal.
 * Payload hanya ID foto. Retry aman: foto yang sudah siap/gagal dilewati, nama hasil
 * deterministik sehingga percobaan ulang menimpa berkas yang sama, dan status hanya berubah
 * di bawah row lock. Berkas yang tidak dapat dibaca langsung gagal (tidak di-retry).
 */
class ProcessComplaintPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public string $fotoId)
    {
        $this->onQueue('media');
        if (SharedHosting::enabled()) {
            $this->timeout = 40;
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(): void
    {
        // Jalur inline setelah response tidak melewati queue:work, jadi alarm worker tidak terpasang.
        SharedHosting::armOperationDeadlines();
        $foto = FotoPengaduan::find($this->fotoId);
        if (! $foto || $foto->status !== 'diproses') {
            return;
        }
        // Decode ≤50 MP di GD membutuhkan lebih dari batas default request; hanya proses worker.
        if (! SharedHosting::enabled()) {
            ini_set('memory_limit', '640M');
        }
        FotoPengaduan::whereKey($foto->id)->update(['percobaan' => DB::raw('percobaan + 1'), 'updated_at' => now()]);

        $source = $foto->storage_key;
        $target = 'pengaduan/'.$foto->id.'.jpg';
        $result = Photo::transcode($source, $target);

        $applied = DB::transaction(function () use ($foto, $result, $target) {
            $locked = FotoPengaduan::whereKey($foto->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'diproses') {
                return false;
            }
            $locked->forceFill($result
                ? $result + ['storage_key' => $target, 'status' => 'siap', 'kode_gagal' => null]
                : ['status' => 'gagal', 'kode_gagal' => 'UNREADABLE'])->save();

            return true;
        });

        if ($applied) {
            Storage::disk(Photo::DISK)->delete($source);
        }
    }

    /** Percobaan habis: tandai gagal; berkas mentah dibuang, laporan tetap utuh. */
    public function failed(?Throwable $e): void
    {
        self::markFailed($this->fotoId, 'PROCESSING_FAILED');
        Log::error('media.processing_failed', ['foto_id' => $this->fotoId, 'exception' => $e ? $e::class : null]);
    }

    public static function markFailed(string $fotoId, string $code): void
    {
        $source = DB::transaction(function () use ($fotoId, $code) {
            $locked = FotoPengaduan::whereKey($fotoId)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'diproses') {
                return null;
            }
            $locked->forceFill(['status' => 'gagal', 'kode_gagal' => $code])->save();

            return $locked->storage_key;
        });
        if ($source && str_starts_with($source, Photo::STAGING.'/')) {
            Storage::disk(Photo::DISK)->delete($source);
        }
    }
}
