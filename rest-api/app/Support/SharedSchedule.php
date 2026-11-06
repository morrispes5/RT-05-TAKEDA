<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Jadwal profil shared, dijalankan DI DALAM proses `rt05:shared-tick`.
 *
 * Biznet PHP 8.4 memblokir proc_open/exec untuk web dan CLI (6 Nov 2026), sedangkan `schedule:run` Laravel menjalankan
 * setiap command terjadwal sebagai proses baru (Symfony Process). Di hosting itu command terjadwal tidak pernah berjalan.
 * Di sini tiap tugas jatuh tempo dipanggil dengan Artisan::call dalam proses yang sama. Aturan jatuh tempo sama dengan
 * sebelumnya (catch-up: dicek tiap tick, jalan bila slot/interval belum tercatat). Profil VPS tetap memakai scheduler.
 */
final class SharedSchedule
{
    /** Kunci tumpang tindih per tugas (detik); setara withoutOverlapping(60) sebelumnya. */
    public const LOCK_SECONDS = 3600;

    /**
     * @return list<array{task:string, command:string, due:callable():bool, mark:callable():void, markOnFailure:bool}>
     */
    public static function tasks(): array
    {
        $wib = fn () => CarbonImmutable::now('Asia/Jakarta');
        $daily = fn (string $task, int $fromHour) => [
            'due' => fn () => $wib()->hour >= $fromHour && SharedHosting::dueSlot($task, $wib()->toDateString()),
            'mark' => fn () => SharedHosting::markRan($task, $wib()->toDateString()),
        ];
        $hourly = fn (string $task) => [
            'due' => fn () => SharedHosting::dueEvery($task, 3600),
            'mark' => fn () => SharedHosting::markRan($task),
        ];

        return [
            ['task' => 'tagihan-generate', 'command' => 'rt05:tagihan-generate', 'markOnFailure' => false] + $daily('tagihan-generate', 0),
            ['task' => 'pengingat-agenda', 'command' => 'rt05:pengingat-agenda', 'markOnFailure' => false] + $hourly('pengingat-agenda'),
            ['task' => 'token-rotate', 'command' => 'rt05:token-rotate --if-due', 'markOnFailure' => false] + $hourly('token-rotate'),
            // Tanggal 5 dan 20 mulai 08:00 WIB: tick pertama setelahnya mengirim; dedup slot per tanggal.
            ['task' => 'pengingat-iuran', 'command' => 'rt05:pengingat-iuran', 'markOnFailure' => false,
                'due' => fn () => in_array($wib()->day, [5, 20], true) && $wib()->hour >= 8
                    && SharedHosting::dueSlot('pengingat-iuran', $wib()->toDateString()),
                'mark' => fn () => SharedHosting::markRan('pengingat-iuran', $wib()->toDateString())],
            // Ketidakcocokan (exit 1) cukup dilaporkan sekali per hari, bukan tiap tick.
            ['task' => 'rekonsiliasi-keuangan', 'command' => 'rt05:rekonsiliasi-keuangan', 'markOnFailure' => true] + $daily('rekonsiliasi-keuangan', 1),
            ['task' => 'anonimkan-arsip', 'command' => 'rt05:anonimkan-arsip', 'markOnFailure' => false] + $daily('anonimkan-arsip', 2),
            ['task' => 'bersihkan-ekspor', 'command' => 'rt05:bersihkan-ekspor', 'markOnFailure' => false] + $daily('bersihkan-ekspor', 2),
        ];
    }

    /**
     * Jalankan tugas yang jatuh tempo. Hanya membaca cache file bila tidak ada yang jatuh tempo (tidak membangunkan Neon).
     *
     * @return list<array{task:string, exit:int}>
     */
    public static function runDue(): array
    {
        $ran = [];
        foreach (self::tasks() as $t) {
            if (! ($t['due'])()) {
                continue;
            }
            $lock = Cache::store('file')->lock('rt05:shared:task:'.$t['task'], self::LOCK_SECONDS);
            if (! $lock->get()) {
                continue;
            }
            try {
                $exit = Artisan::call($t['command']);
            } catch (Throwable $e) {
                report($e);
                $exit = 1;
            } finally {
                $lock->release();
            }
            if ($exit === 0 || $t['markOnFailure']) {
                ($t['mark'])();
            }
            $ran[] = ['task' => $t['task'], 'exit' => $exit];
        }

        return $ran;
    }
}
