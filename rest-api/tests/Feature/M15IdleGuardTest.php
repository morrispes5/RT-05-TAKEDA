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

    public function test_overlap_mutexes_are_short_so_a_killed_run_cannot_stall_the_outbox_for_a_day(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            if ($event->withoutOverlapping) {
                $this->assertLessThanOrEqual(60, $event->expiresAt, (string) $event->command);
            }
        }
        $dispatch = $this->event('rt05:outbox-dispatch');
        $this->assertSame(5, $dispatch->expiresAt);

        // Deploy membunuh scheduler di tengah run: mutex tertinggal, lalu kedaluwarsa dalam 5 menit.
        Cache::flush();
        IdleGuard::touch();
        $dispatch->mutex->create($dispatch);
        $this->assertFalse($dispatch->filtersPass($this->app));
        $this->travel(6)->minutes();
        IdleGuard::touch();
        $this->assertTrue($dispatch->filtersPass($this->app));
    }

    /**
     * #46: backoff enqueue (maks 15 menit) dan ambang stale (900 detik) lebih panjang dari ACTIVE_SECONDS.
     * Selama masih ada event yang belum selesai, dispatcher harus tetap dijadwalkan tiap menit walau tidak
     * ada event baru; begitu semuanya selesai, guard boleh idle lagi (Neon tidur).
     */
    public function test_pending_outbox_work_keeps_dispatcher_awake_past_active_window_then_goes_idle(): void
    {
        Cache::flush();
        foreach (['outbox-dispatch', 'media-recover', 'push-recover'] as $task) {
            IdleGuard::markRan($task);
        }
        $event = DB::transaction(fn () => Outbox::record('uji.idle', 'uji', null));
        // Enqueue gagal berulang: event baru boleh dicoba lagi 15 menit kemudian (backoff maksimum).
        DB::table('outbox_events')->where('id', $event->id)->update(['available_at' => DB::raw("now() + interval '15 minutes'")]);
        $dispatch = $this->event('rt05:outbox-dispatch');

        // Tiap menit scheduler menjalankan dispatcher; tidak ada yang diklaim karena belum jatuh tempo.
        for ($minute = 1; $minute <= 14; $minute++) {
            $this->travel(1)->minutes();
            $this->assertTrue($dispatch->filtersPass($this->app), "menit $minute: event tertunda harus tetap dipantau");
            $this->artisan('rt05:outbox-dispatch')->assertExitCode(0);
        }

        // Job published tetapi hilang (belum completed): pemulihan stale harus tetap berjalan tanpa event baru.
        DB::table('outbox_events')->where('id', $event->id)->update(['published_at' => DB::raw('now()'), 'available_at' => DB::raw('now()')]);
        $this->travel(1)->minutes();
        $this->artisan('rt05:outbox-dispatch')->assertExitCode(0);
        $this->travel(IdleGuard::ACTIVE_SECONDS - 60)->seconds();
        $this->assertTrue($dispatch->filtersPass($this->app), 'event published tanpa completed tetap dipantau');

        // Semua selesai: tidak ada kerja tertunda, guard tidak diperpanjang dan Neon boleh tidur.
        DB::table('outbox_events')->where('id', $event->id)->update(['completed_at' => DB::raw('now()')]);
        $this->artisan('rt05:outbox-dispatch')->assertExitCode(0);
        $this->travel(IdleGuard::ACTIVE_SECONDS + 1)->seconds();
        $this->assertFalse(IdleGuard::active());
        $this->assertFalse($dispatch->filtersPass($this->app));
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
