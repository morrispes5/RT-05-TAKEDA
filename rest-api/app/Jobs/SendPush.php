<?php

namespace App\Jobs;

use App\Domain\Community\Push\PushGateway;
use App\Domain\Community\PushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

/**
 * Push FCM untuk satu event outbox, setelah inbox commit (antrean notifications). Sama di VPS
 * (Redis) maupun shared (antrean database setelah response + tick cron). Penerima diproses per
 * kelompok dalam batas waktu; sisa dilanjutkan job baru. Provider sementara gagal → release dengan
 * backoff; percobaan habis → push_status gagal. Event outbox dan inbox tidak pernah disentuh.
 */
class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    /** @var list<int> */
    public const RETRY_DELAYS = [30, 120, 600];

    public function __construct(public string $eventId) {}

    public function handle(PushGateway $gateway): void
    {
        try {
            $result = PushDelivery::deliver($this->eventId, $gateway);
        } catch (Throwable $e) {
            // Pesan QueryException memuat binding (ID penerima); hanya nama kelas ke failed_jobs/log.
            throw new RuntimeException('push_failed:'.class_basename($e));
        }

        if ($result === 'more') {
            self::queueFor($this->eventId);
        } elseif ($result === 'retry') {
            if ($this->attempts() >= $this->tries) {
                PushDelivery::giveUp($this->eventId);
            } else {
                $this->release(self::RETRY_DELAYS[min($this->attempts(), count(self::RETRY_DELAYS)) - 1]);
            }
        }
    }

    public function failed(?Throwable $e): void
    {
        PushDelivery::giveUp($this->eventId);
        Log::warning('push.job_failed', ['event_id' => $this->eventId, 'exception' => $e ? class_basename($e) : null]);
    }

    /** Enqueue gagal tidak boleh menggagalkan event: rt05:push-recover mengulang nanti. */
    public static function queueFor(string $eventId): void
    {
        try {
            Queue::connection(config('rt05.outbox.queue_connection'))
                ->pushOn(config('rt05.outbox.queue', 'notifications'), new self($eventId));
        } catch (Throwable $e) {
            Log::warning('push.enqueue_failed', ['event_id' => $eventId, 'exception' => class_basename($e)]);
        }
    }
}
