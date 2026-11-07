<?php

namespace App\Console\Commands;

use App\Domain\Community\Push\FcmGateway;
use App\Domain\Community\Push\PushGateway;
use Illuminate\Console\Command;

/**
 * M16.5: cek push FCM dari server (lewat cron sekali jalan di hosting tanpa SSH). Tidak mengirim ke perangkat mana pun:
 * memakai token sintetis + validate_only. Output tanpa kredensial atau token akses.
 */
class PushCheck extends Command
{
    protected $signature = 'rt05:push-check';

    protected $description = 'Cek kredensial FCM dan jalur HTTPS ke FCM tanpa mengirim notifikasi';

    public function handle(PushGateway $gateway): int
    {
        if (! $gateway instanceof FcmGateway) {
            $this->error('push=nonaktif driver='.config('rt05.push.driver').' (inbox saja)');

            return self::FAILURE;
        }
        $result = $gateway->probe();
        $result['ok'] ? $this->line($result['line']) : $this->error($result['line']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
