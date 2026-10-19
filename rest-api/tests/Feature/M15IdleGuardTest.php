<?php

namespace Tests\Feature;

use App\Support\IdleGuard;
use App\Support\Outbox;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M15 (ADR31): profil VPS tidak menanyai database tiap menit saat idle, agar compute Neon bisa tidur
 * (kuota 100 CU-jam/bulan per project dipakai bersama staging dan production).
 */
class M15IdleGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected array $connectionsToTransact = ['pgsql'];

    private function event(string $command)
    {
        return collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, $command));
    }

    public function test_db_sweeps_skip_while_idle_and_resume_on_new_outbox_event_or_hourly_safety(): void
    {
        Cache::flush();
        foreach (['outbox-dispatch', 'media-recover', 'push-recover'] as $task) {
            IdleGuard::markRan($task);
        }
        foreach (['rt05:outbox-dispatch', 'rt05:media-recover', 'rt05:push-recover'] as $command) {
            $this->assertFalse($this->event($command)->filtersPass($this->app), "$command tidak jalan saat idle");
        }

        // Event baru commit → aktif: outbox tiap menit lagi (notifikasi ≤60 detik).
        DB::transaction(fn () => Outbox::record('uji.idle', 'uji', null));
        $this->assertTrue(IdleGuard::active());
        $this->assertTrue($this->event('rt05:outbox-dispatch')->filtersPass($this->app));

        // Setelah 10 menit tanpa event: idle lagi; pengaman per jam tetap jalan.
        $this->travel(IdleGuard::ACTIVE_SECONDS + 1)->seconds();
        $this->assertFalse(IdleGuard::active());
        $this->travel(1)->hours();
        $this->assertTrue($this->event('rt05:media-recover')->filtersPass($this->app));
    }

    public function test_rolled_back_transaction_does_not_mark_activity(): void
    {
        Cache::flush();
        try {
            DB::transaction(function () {
                Outbox::record('uji.idle', 'uji', null);
                throw new \RuntimeException('batal');
            });
        } catch (\RuntimeException) {
        }
        $this->assertFalse(IdleGuard::active());
    }
}
