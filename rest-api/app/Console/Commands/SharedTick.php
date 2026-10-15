<?php

namespace App\Console\Commands;

use App\Domain\Operations\OutboxDispatcher;
use App\Support\Heartbeat;
use App\Support\SharedHosting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Throwable;

/** One CLI cron per minute, same persistent storage and Unix user as PHP web. */
class SharedTick extends Command
{
    protected $signature = 'rt05:shared-tick';

    protected $description = 'Jalankan scheduler dan worker shared hosting secara terbatas';

    public function handle(): int
    {
        if (! SharedHosting::enabled() || config('queue.default') !== 'database'
            || (config('rt05.outbox.queue_connection') ?? config('queue.default')) !== 'database'
            || config('rt05.heartbeat.store') !== 'file'
            || config('cache.default') !== 'file' || config('session.driver') !== 'file'
            || ! function_exists('pcntl_alarm')) {
            $this->error('Memerlukan profil shared, queue database, cache/session file, dan pcntl CLI.');

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
            if ($this->call('schedule:run') !== 0) {
                return self::FAILURE;
            }
            $cache = Cache::store('file');
            $sweep = time() - (int) $cache->get('rt05:shared:last-sweep', 0) >= 1800;
            if (! $sweep && ! SharedHosting::awake()) {
                Heartbeat::beat('shared-tick');

                return self::SUCCESS;
            }

            if ($sweep && $this->call('rt05:media-recover') !== 0) {
                return self::FAILURE;
            }
            $result = OutboxDispatcher::dispatch(25);
            if ($result['failed'] > 0) {
                SharedHosting::wake();

                return self::FAILURE;
            }
            // max-time is checked between jobs; a running job can add up to its timeout.
            $exit = $this->call('queue:work', [
                'connection' => 'database',
                '--queue' => 'default,notifications,media,exports',
                '--stop-when-empty' => true,
                '--max-time' => 40,
                '--max-jobs' => 25,
                '--tries' => 5,
                '--timeout' => 60,
                '--sleep' => 1,
            ]);
            if ($exit !== 0) {
                SharedHosting::wake();

                return self::FAILURE;
            }
            // Include delayed/reserved jobs so retries get another short tick, not a 30m wait.
            foreach (['default', 'notifications', 'media', 'exports'] as $queue) {
                if (Queue::connection('database')->size($queue) > 0) {
                    SharedHosting::wake();
                    break;
                }
            }
            if ($result['claimed'] === 25) {
                SharedHosting::wake();
            }
            if ($sweep) {
                $cache->put('rt05:shared:last-sweep', time(), 86400);
            }
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
