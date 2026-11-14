<?php

namespace App\Console\Commands;

use App\Domain\Operations\OutboxDispatcher;
use App\Support\Database\NeonBridge;
use App\Support\Heartbeat;
use App\Support\SharedHosting;
use App\Support\SharedSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Cron jaring 10–15 menit. Boleh tiap menit: tick idle tidak membuka database. */
class SharedTick extends Command
{
    protected $signature = 'rt05:shared-tick';

    protected $description = 'Jalankan scheduler dan pemulihan shared hosting secara terbatas';

    public function handle(): int
    {
        if (! SharedHosting::enabled() || config('queue.default') !== 'database'
            || (config('rt05.outbox.queue_connection') ?? config('queue.default')) !== 'database'
            || config('rt05.heartbeat.store') !== 'file'
            || config('cache.default') !== 'file' || config('session.driver') !== 'file') {
            $this->error('Memerlukan profil shared, queue database, serta cache/session/heartbeat file.');

            return self::INVALID;
        }

        // OS releases flock even if a worker timeout kills this process. Never a DB lock.
        $lock = fopen(storage_path('framework/shared-tick.lock'), 'c');
        if ($lock === false) {
            return self::FAILURE;
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return self::SUCCESS;
        }

        try {
            // Watchdog ADR55: cek flock + socket saja (tidak membuka Neon). Jembatan mati → dinyalakan lagi di sini,
            // jadi request berikutnya tidak menanggung waktu start. Gagal → langkah database di bawah ikut gagal.
            if (! app(NeonBridge::class)->ensureRunning()) {
                $this->error('neon_bridge_unavailable');
            }
            // Bukan `schedule:run`: itu menjalankan command sebagai proses baru (proc_open), yang diblokir di Biznet.
            $sweep = SharedHosting::sweepDue();
            foreach (SharedSchedule::runDue($sweep) as $ran) {
                $this->line(now()->toDateTimeString().' '.$ran['task'].' exit='.$ran['exit']);
            }
            $cache = Cache::store('file');
            if (! $sweep && ! SharedHosting::awake()) {
                // Idle juga baru menulis setelah tugas terjadwal kembali.
                Heartbeat::beat('shared-tick');

                return self::SUCCESS;
            }

            if ($sweep && ($this->call('rt05:media-recover') !== 0 || $this->call('rt05:push-recover') !== 0)) {
                return self::FAILURE;
            }
            $result = OutboxDispatcher::dispatch(25);
            if ($result['failed'] > 0) {
                SharedHosting::wake();

                return self::FAILURE;
            }
            $exit = $this->call('queue:work', SharedHosting::workerArguments(40));
            if ($exit !== 0 || SharedHosting::backlog() || $result['claimed'] === 25) {
                SharedHosting::wake();
                if ($exit !== 0) {
                    return self::FAILURE;
                }
            }
            if ($sweep) {
                $cache->put('rt05:shared:last-sweep', time(), 86400);
            }
            // Beat hanya setelah tick selesai. Tick yang macet tidak memperbarui
            // penanda, sehingga umur heartbeat melewati interval cron + toleransi.
            Heartbeat::beat('shared-tick');

            return self::SUCCESS;
        } catch (Throwable $e) {
            SharedHosting::wake();
            $this->error('shared_tick_failed:'.class_basename($e));

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
