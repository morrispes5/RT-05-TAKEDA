<?php

namespace Tests\Feature;

use App\Domain\Community\Emergencies;
use App\Jobs\ProcessOutboxEvent;
use App\Models\Akun;
use App\Models\OutboxEvent;
use App\Support\Outbox;
use App\Support\Privacy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Uji production 8 Nov 2026: alarm darurat sampai ke HP 30–60 detik karena menunggu sweep outbox tiap menit.
 * Profil VPS kini meng-enqueue event darurat.* segera setelah response; event lain tetap lewat sweep.
 */
class M19UrgentDispatchTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['panic.enabled' => true, 'panic.audience' => 'all', 'rt05.deployment_profile' => 'vps',
            'rt05.outbox.urgent_immediate' => true]);
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":true}']);
        Queue::fake();
    }

    private function consent(Akun $a): Akun
    {
        $a->forceFill(['privasi_versi' => Privacy::PANIC_VERSION, 'privasi_disetujui_at' => now()])->save();

        return $a;
    }

    public function test_emergency_event_is_enqueued_right_after_the_response(): void
    {
        $a = $this->consent($this->warga('cepat1'));
        $id = $this->as($a)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/emergencies', [
            'konfirmasi' => true, 'versi_ketentuan' => Emergencies::TERMS])->assertCreated()->json('data.id');

        $event = OutboxEvent::where('type', 'darurat.dibuat')->where('aggregate_id', $id)->firstOrFail();
        $this->assertNotNull($event->published_at, 'Event darurat harus langsung di-enqueue, tidak menunggu sweep tiap menit.');
        Queue::assertPushedOn('notifications', ProcessOutboxEvent::class);
    }

    public function test_non_emergency_events_still_wait_for_the_scheduler_sweep(): void
    {
        $this->assertFalse(Outbox::urgent('iuran.pembayaran_dicatat'));
        $this->assertTrue(Outbox::urgent('darurat.dibatalkan_pelapor'));
        config(['rt05.outbox.urgent_immediate' => false]);
        $this->assertFalse(Outbox::urgent('darurat.dibuat'));
    }
}
