<?php

namespace App\Console\Commands;

use App\Support\Database\NeonBridge;
use Illuminate\Console\Command;

/** Operator jembatan Neon ADR55. Tidak membuka database; status hanya membaca flock, socket, dan log. */
class NeonBridgeCommand extends Command
{
    protected $signature = 'rt05:neon-bridge {aksi=status : status|start|stop}';

    protected $description = 'Status, nyalakan, atau hentikan jembatan PostgreSQL→Neon lewat WebSocket (ADR55)';

    public function handle(NeonBridge $bridge): int
    {
        if (! $bridge->enabled()) {
            $this->line('jembatan=mati (RT05_NEON_BRIDGE bukan true)');

            return self::SUCCESS;
        }
        foreach ($bridge->configErrors() as $error) {
            $this->error($error);
        }

        switch ($this->argument('aksi')) {
            case 'start':
                $ok = $bridge->ensureRunning();
                $this->line($ok ? 'hidup pid='.$bridge->pid() : 'gagal menyalakan (lihat log Laravel dan bridge.log)');

                return $ok ? self::SUCCESS : self::FAILURE;
            case 'stop':
                // Koneksi aktif ikut putus; request berikutnya atau tick menit berikutnya menyalakan lagi.
                $ok = $bridge->stop();
                $this->line($ok ? 'dihentikan' : 'tidak berjalan');

                return self::SUCCESS;
            case 'status':
                $running = $bridge->running();
                $this->line('hidup='.($running ? 'ya pid='.$bridge->pid() : 'tidak')
                    .' socket='.(file_exists($bridge->socketPath()) ? 'ya' : 'tidak'));
                foreach ($bridge->logTail() as $line) {
                    $this->line('  '.$line);
                }

                return $running ? self::SUCCESS : self::FAILURE;
            default:
                $this->error('aksi: status|start|stop');

                return self::INVALID;
        }
    }
}
