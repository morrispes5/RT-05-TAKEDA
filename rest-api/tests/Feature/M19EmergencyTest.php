<?php

namespace Tests\Feature;

use App\Domain\Community\Emergencies;
use App\Domain\Community\Push\FcmGateway;
use App\Domain\Community\Push\PushGateway;
use App\Domain\Community\Push\PushMessage;
use App\Domain\Community\PushDelivery;
use App\Jobs\ProcessOutboxEvent;
use App\Models\Akun;
use App\Models\KejadianDarurat;
use App\Models\Notifikasi;
use App\Models\OutboxEvent;
use App\Support\Privacy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

class M19EmergencyTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['panic.enabled' => true, 'panic.audience' => 'all']);
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":true}']);
        Queue::fake();
    }

    private function consent(Akun $a): Akun
    {
        $a->forceFill(['privasi_versi' => Privacy::PANIC_VERSION, 'privasi_disetujui_at' => now()])->save();

        return $a;
    }

    private function send(Akun $a, ?string $key = null, array $body = [])
    {
        return $this->as($a)->withHeader('Idempotency-Key', $key ?? Str::uuid())->postJson('/api/v1/emergencies', $body + [
            'jenis' => 'kebakaran', 'konfirmasi' => true, 'versi_ketentuan' => Emergencies::TERMS]);
    }

    private function resolve(Akun $admin, string $id, bool $misuse = false, string $status = 'selesai', ?string $key = null)
    {
        return $this->as($admin)->withHeader('Idempotency-Key', $key ?? Str::uuid())->postJson("/api/v1/admin/emergencies/$id/resolve", [
            'versi' => 1, 'status' => $status, 'penyalahgunaan' => $misuse, 'alasan' => 'Penilaian pengurus sintetis']);
    }

    public function test_confirmed_report_is_atomic_idempotent_and_uses_server_identity(): void
    {
        $a = $this->consent($this->warga('panic1'));
        $b = $this->consent($this->warga('panic2'));
        $key = (string) Str::uuid();
        $res = $this->send($a, $key, ['pelapor_id' => $b->id, 'rumah_id' => $b->rumah()->id])->assertCreated();
        $id = $res->json('data.id');
        $this->assertSame($a->nama, $res->json('data.pelapor'));
        $this->assertSame($a->rumah()->id, KejadianDarurat::findOrFail($id)->rumah_id);
        $this->assertSame([], OpenApi::errors($res->json(), 'EmergencyEnvelope'));
        $this->send($a, $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $id);
        $this->send($a)->assertCreated()->assertJsonPath('data.id', $id);
        $this->send($a, $key, ['jenis' => 'kemalingan'])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
        $this->send($a, null, ['jenis' => 'kemalingan'])->assertStatus(409);
        $this->assertSame(1, KejadianDarurat::count());
        $this->assertSame(1, OutboxEvent::where('type', 'darurat.dibuat')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'darurat.dibuat')->count());
        $cached = DB::table('idempotency_requests')->where('route_scope', 'darurat.buat')->pluck('response_body')->implode('');
        $this->assertStringNotContainsString($a->nama, $cached);
        $this->assertStringNotContainsString($a->rumah()->alamat(), $cached);
    }

    public function test_unconfirmed_bad_terms_invalid_kind_missing_key_and_unlinked_house_do_not_send(): void
    {
        $a = $this->consent($this->warga('panic3'));
        foreach ([['konfirmasi' => false], ['versi_ketentuan' => 'lama'], ['jenis' => 'banjir_teks_bebas']] as $data) {
            $this->send($a, null, $data)->assertStatus(422);
        }
        $this->send($a, 'bukan-uuid')->assertStatus(422);
        $this->send($this->consent($this->pengurus()))->assertStatus(422)->assertJsonPath('error.code', 'PANIC_HOUSE_REQUIRED');
        $this->assertSame(0, KejadianDarurat::count());
        $this->assertSame(0, OutboxEvent::where('type', 'darurat.dibuat')->count());
    }

    public function test_inactive_pending_unconsented_and_disabled_features_fail_closed(): void
    {
        $a = $this->warga('panic4');
        $this->send($a)->assertForbidden()->assertJsonPath('error.code', 'PRIVACY_CONSENT_REQUIRED');
        foreach (['menunggu', 'nonaktif', 'diarsipkan'] as $s) {
            $a->forceFill(['status' => $s, 'privasi_versi' => Privacy::PANIC_VERSION])->save();
            $this->send($a)->assertForbidden();
        }
        $a->forceFill(['status' => 'aktif'])->save();
        config(['panic.enabled' => false]);
        $this->send($a)->assertForbidden();
        config(['panic.enabled' => true]);
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":false}']);
        $this->send($a)->assertForbidden()->assertJsonPath('error.code', 'PANIC_DISABLED');
        $this->assertSame(0, KejadianDarurat::count());
    }

    public function test_latest_privacy_text_must_actually_be_displayed_before_consent(): void
    {
        $a = $this->warga('panic5');
        $this->as($a)->postJson('/api/v1/auth/privacy-consent', ['versi' => Privacy::PANIC_VERSION])->assertStatus(422);
        $this->postJson('/api/v1/auth/privacy-consent', ['versi' => Privacy::PANIC_VERSION, 'versi_teks' => Privacy::PANIC_VERSION])
            ->assertOk()->assertJsonPath('data.privasi_perlu_setuju', false);
        config(['panic.enabled' => false, 'finance.v2' => false]);
        $this->assertSame(Privacy::VERSION, Privacy::version());
        config(['finance.v2' => true]);
        $this->assertSame(Privacy::FINANCE_VERSION, Privacy::version());
    }

    public function test_fanout_is_unique_private_and_excludes_pending_and_old_consent(): void
    {
        $a = $this->consent($this->warga('panic6'));
        $admin = $this->consent($this->pengurus());
        $old = $this->warga('panic7');
        $pending = $this->pemohon('panic6', 'pending-panic@contoh.test');
        $e = Emergencies::create($a, 'kebakaran');
        $out = OutboxEvent::where('type', 'darurat.dibuat')->where('aggregate_id', $e->id)->firstOrFail();
        (new ProcessOutboxEvent($out->id))->handle();
        (new ProcessOutboxEvent($out->id))->handle();
        $this->assertSame(2, Notifikasi::where('event_id', $out->id)->count());
        $this->assertSame(0, Notifikasi::whereIn('penerima_id', [$old->id, $pending->id])->where('jenis', 'darurat')->count());
        $serialized = Notifikasi::where('event_id', $out->id)->get()->toJson().json_encode($out->safe_payload);
        $this->assertStringNotContainsString($a->nama, $serialized);
        $this->assertStringNotContainsString($a->rumah()->alamat(), $serialized);
        $this->as($admin)->getJson('/api/v1/emergencies/'.$e->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_restricted_audience_never_broadcasts_to_other_accounts(): void
    {
        $a = $this->consent($this->warga('panic8'));
        $b = $this->consent($this->warga('panic9'));
        config(['panic.audience' => 'restricted', 'panic.recipient_ids' => [$a->id]]);
        $e = Emergencies::create($a, 'kemalingan');
        $out = OutboxEvent::where('aggregate_id', $e->id)->firstOrFail();
        (new ProcessOutboxEvent($out->id))->handle();
        $this->assertSame([$a->id], Notifikasi::where('event_id', $out->id)->pluck('penerima_id')->all());
        $this->as($b)->getJson('/api/v1/emergencies/'.$e->id)->assertNotFound();
        config(['panic.recipient_ids' => []]);
        $this->send($b)->assertForbidden();
    }

    public function test_closed_stale_and_disabled_reports_never_create_late_alarms(): void
    {
        $a = $this->consent($this->warga('panic10'));
        $admin = $this->consent($this->pengurus());
        foreach (['closed', 'stale', 'disabled'] as $mode) {
            $e = Emergencies::create($a, 'kebakaran');
            $out = OutboxEvent::where('aggregate_id', $e->id)->where('type', 'darurat.dibuat')->firstOrFail();
            if ($mode === 'closed') {
                Emergencies::resolve($admin, $e->id, 1, 'selesai', false, 'Sudah ditangani');
            } elseif ($mode === 'stale') {
                $this->travel(11)->minutes();
            } else {
                Emergencies::configure($admin, 1, false);
            }
            (new ProcessOutboxEvent($out->id))->handle();
            $this->assertSame(0, Notifikasi::where('event_id', $out->id)->count());
            if ($mode !== 'closed') {
                Emergencies::resolve($admin, $e->id, 1, 'selesai', false, 'Sudah ditangani');
            }
        }
    }

    public function test_three_explicit_misuse_assessments_disable_only_the_sender_and_do_not_double_count(): void
    {
        $a = $this->consent($this->warga('panic11'));
        $admin = $this->consent($this->pengurus());
        // A false report without an explicit abuse assessment does not increase the count.
        $id = Emergencies::create($a, 'lainnya')->id;
        $this->resolve($admin, $id, false, 'palsu')->assertOk();
        $this->assertSame(0, Emergencies::misuseCount($a->id));
        config(['panic.account_hourly_limit' => 10]);
        for ($i = 1; $i <= 3; $i++) {
            $id = Emergencies::create($a, 'lainnya')->id;
            $key = (string) Str::uuid();
            $this->resolve($admin, $id, true, 'palsu', $key)->assertOk();
            $this->resolve($admin, $id, true, 'palsu', $key)->assertOk()->assertHeader('Idempotent-Replayed', 'true');
            $this->resolve($admin, $id, true, 'palsu')->assertConflict();
            $this->assertSame($i, Emergencies::misuseCount($a->id));
        }
        $this->send($a)->assertForbidden()->assertJsonPath('error.code', 'PANIC_ACCOUNT_DISABLED');
        $b = $this->consent($this->warga('panic12'));
        $this->send($b)->assertCreated();
    }

    public function test_resident_cannot_resolve_or_change_settings_and_admin_changes_are_versioned(): void
    {
        $a = $this->consent($this->warga('panic13'));
        $admin = $this->consent($this->pengurus());
        $e = Emergencies::create($a, 'penyerangan');
        $this->resolve($a, $e->id)->assertForbidden();
        $this->as($a)->postJson('/api/v1/admin/emergencies/settings', ['versi' => 1, 'aktif' => false])->assertForbidden();
        $this->resolve($admin, $e->id, true)->assertStatus(422);
        $this->resolve($admin, $e->id)->assertOk();
        $this->as($a)->getJson('/api/v1/emergencies/'.$e->id)->assertOk()->assertJsonMissingPath('data.catatan_pengurus')->assertJsonMissingPath('data.penyalahgunaan');
        $key = (string) Str::uuid();
        $this->as($admin)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/emergencies/settings', ['versi' => 1, 'aktif' => false])->assertOk()->assertJsonPath('data.aktif', false);
        $this->postJson('/api/v1/admin/emergencies/settings', ['versi' => 1, 'aktif' => false])->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->withHeader('Idempotency-Key', Str::uuid())->postJson('/api/v1/admin/emergencies/settings', ['versi' => 1, 'aktif' => true])->assertConflict();
    }

    public function test_database_rate_limit_survives_cache_reset_and_takes_retry_before_budget(): void
    {
        $a = $this->consent($this->warga('panic14'));
        $admin = $this->consent($this->pengurus());
        for ($i = 0; $i < 3; $i++) {
            $id = $this->send($a)->assertCreated()->json('data.id');
            $this->send($a)->assertCreated()->assertJsonPath('data.id', $id);
            $this->resolve($admin, $id)->assertOk();
        }
        Cache::flush();
        $this->send($a)->assertStatus(429);
        $this->travel(61)->minutes();
        $this->send($a)->assertCreated();
    }

    public function test_alarm_payload_uses_separate_channel_short_lifetime_and_private_visibility(): void
    {
        $p = FcmGateway::payload(new PushMessage('synthetic', 'Darurat', 'Buka aplikasi', [], 'rt05-unique', true));
        $this->assertSame('rt05_darurat_v1', $p['android']['notification']['channel_id']);
        $this->assertSame('PRIVATE', $p['android']['notification']['visibility']);
        $this->assertSame('600s', $p['android']['ttl']);
        $late = FcmGateway::payload(new PushMessage('synthetic', 'Darurat', 'Buka aplikasi', [], 'rt05-late', true, 20));
        $this->assertSame('20s', $late['android']['ttl']);
        $normal = FcmGateway::payload(new PushMessage('synthetic', 'Info', 'Buka aplikasi', [], 'normal'));
        $this->assertArrayNotHasKey('channel_id', $normal['android']['notification']);
        $this->assertSame('86400s', $normal['android']['ttl']);
    }

    public function test_push_is_suppressed_if_feature_is_disabled_after_inbox_commit(): void
    {
        $a = $this->consent($this->warga('panic15'));
        $e = Emergencies::create($a, 'kebakaran');
        $out = OutboxEvent::where('aggregate_id', $e->id)->firstOrFail();
        (new ProcessOutboxEvent($out->id))->handle();
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":false}']);
        $gateway = new class implements PushGateway
        {
            public function configured(): bool
            {
                return true;
            }

            public function send(array $messages): array
            {
                if ($messages !== []) {
                    throw new \RuntimeException('Alarm harus dibatalkan');
                }

                return [];
            }
        };
        $this->assertSame('done', PushDelivery::deliver($out->id, $gateway));
        $this->assertSame(0, Notifikasi::where('event_id', $out->id)->whereNull('push_status')->count());
        $this->assertSame(1, KejadianDarurat::count());
    }

    public function test_database_guard_preserves_original_report_and_terminal_assessment(): void
    {
        $a = $this->consent($this->warga('panic16'));
        $admin = $this->consent($this->pengurus());
        $e = Emergencies::create($a, 'kebakaran');
        foreach (['rewrite', 'delete', 'invalid-status'] as $action) {
            try {
                DB::transaction(function () use ($e, $action) {
                    $q = DB::table('kejadian_darurat')->where('id', $e->id);
                    if ($action === 'delete') {
                        $q->delete();
                    } else {
                        $q->update($action === 'rewrite' ? ['jenis' => 'kemalingan'] : ['status' => 'bukan_status']);
                    }
                });
                $this->fail('Guard harus menolak '.$action);
            } catch (QueryException $error) {
                $this->assertSame('23514', (string) $error->getCode());
            }
        }
        Emergencies::resolve($admin, $e->id, 1, 'palsu', true, 'Penilaian sintetis');
        try {
            DB::transaction(fn () => DB::table('kejadian_darurat')->where('id', $e->id)->update(['penyalahgunaan' => false]));
            $this->fail('Penilaian terminal tidak boleh diedit');
        } catch (QueryException $error) {
            $this->assertSame('23514', (string) $error->getCode());
        }
        $this->assertSame(1, Emergencies::misuseCount($a->id));
    }

    public function test_demoted_actor_does_not_receive_operator_notes_from_a_cached_role(): void
    {
        $a = $this->consent($this->warga('panic17'));
        $admin = $this->consent($this->pengurus());
        $e = Emergencies::create($a, 'lainnya');
        Emergencies::resolve($admin, $e->id, 1, 'palsu', true, 'Catatan pengurus privat');
        DB::table('akun')->where('id', $admin->id)->update(['peran' => 'warga']);
        // The injected auth object still has its previous role; the detail must use the freshly authorised role.
        $this->as($admin)->getJson('/api/v1/emergencies/'.$e->id)->assertOk()->assertJsonMissingPath('data.catatan_pengurus');
    }

    public function test_migration_rollback_refuses_to_erase_existing_emergency_history(): void
    {
        $a = $this->consent($this->warga('panic18'));
        Emergencies::create($a, 'kebakaran');
        $migration = require database_path('migrations/2026_10_27_000100_create_kejadian_darurat.php');
        try {
            $migration->down();
            $this->fail('Rollback harus menolak menghapus riwayat');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Riwayat darurat sudah ada', $error->getMessage());
        }
        $this->assertSame(1, KejadianDarurat::count());
    }

    public function test_api_distinguishes_queue_provider_failure_and_provider_acceptance_from_device_receipt(): void
    {
        $a = $this->consent($this->warga('panic19'));
        $res = $this->send($a)->assertCreated()->assertJsonPath('data.pemberitahuan', 'antrean');
        $id = $res->json('data.id');
        $event = OutboxEvent::where('aggregate_id', $id)->where('type', 'darurat.dibuat')->firstOrFail();
        (new ProcessOutboxEvent($event->id))->handle();
        $this->getJson('/api/v1/emergencies/'.$id)->assertOk()->assertJsonPath('data.pemberitahuan', 'diproses');
        DB::table('notifikasi')->where('event_id', $event->id)->update(['push_status' => 'gagal']);
        $this->getJson('/api/v1/emergencies/'.$id)->assertOk()->assertJsonPath('data.pemberitahuan', 'gagal');
        DB::table('notifikasi')->where('event_id', $event->id)->update(['push_status' => 'terkirim']);
        $this->getJson('/api/v1/emergencies/'.$id)->assertOk()->assertJsonPath('data.pemberitahuan', 'provider_menerima');
    }
}
