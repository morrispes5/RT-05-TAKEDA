<?php

namespace Tests\Feature;

use App\Domain\Community\Emergencies;
use App\Models\KejadianDarurat;
use App\Support\Privacy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommittedData;
use Tests\Support\Concurrent;
use Tests\Support\Fixtures;
use Tests\TestCase;

class M19ConcurrencyTest extends TestCase
{
    use CommittedData, Concurrent, Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markCommittedData();
        config(['panic.enabled' => true, 'panic.audience' => 'all']);
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":true}']);
    }

    protected function tearDown(): void
    {
        $this->purgeCommittedData();
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":false}', 'version' => 1, 'updated_by' => null]);
        parent::tearDown();
    }

    public function test_independent_requests_with_same_or_different_keys_make_one_alarm(): void
    {
        $a = $this->warga('panicrace');
        $a->forceFill(['privasi_versi' => Privacy::PANIC_VERSION])->save();
        $token = $a->createToken('synthetic')->plainTextToken;
        $same = (string) Str::uuid();
        $results = $this->runConcurrently(array_map(fn ($i) => [
            'kind' => 'http', 'method' => 'POST', 'uri' => '/api/v1/emergencies', 'bearer' => $token,
            'body' => ['jenis' => 'kebakaran', 'konfirmasi' => true, 'versi_ketentuan' => Emergencies::TERMS],
            'headers' => ['Idempotency-Key' => $i < 3 ? $same : (string) Str::uuid()], 'label' => 'panic-'.$i,
            'env' => ['RT05_PANIC_ENABLED' => 'true', 'RT05_PANIC_AUDIENCE' => 'all'],
        ], [1, 2, 3]));
        $this->assertCount(3, array_filter($results, fn ($r) => $r['status'] === 201));
        $this->assertCount(1, array_unique(array_column(array_column($results, 'data'), 'id')));
        $this->assertSame(1, KejadianDarurat::count());
        $this->assertSame(1, DB::table('outbox_events')->where('type', 'darurat.dibuat')->count());
    }

    public function test_two_admins_cannot_double_count_the_third_misuse(): void
    {
        $a = $this->warga('panicrace2');
        $a->forceFill(['privasi_versi' => Privacy::PANIC_VERSION])->save();
        $admin = $this->pengurus();
        $admin->forceFill(['privasi_versi' => Privacy::PANIC_VERSION])->save();
        for ($i = 0; $i < 2; $i++) {
            $e = Emergencies::create($a, 'lainnya');
            Emergencies::resolve($admin, $e->id, 1, 'palsu', true, 'Penyalahgunaan sintetis');
        }
        $e = Emergencies::create($a, 'lainnya');
        $tokens = [$admin->createToken('a')->plainTextToken];
        $other = $this->pengurus('kedua-panic@contoh.test');
        $other->forceFill(['privasi_versi' => Privacy::PANIC_VERSION])->save();
        $tokens[] = $other->createToken('b')->plainTextToken;
        $results = $this->runConcurrently(array_map(fn ($t) => [
            'kind' => 'http', 'method' => 'POST', 'uri' => '/api/v1/admin/emergencies/'.$e->id.'/resolve', 'bearer' => $t,
            'body' => ['versi' => 1, 'status' => 'palsu', 'penyalahgunaan' => true, 'alasan' => 'Penyalahgunaan sintetis'],
            'headers' => ['Idempotency-Key' => (string) Str::uuid()], 'label' => 'panic-assess',
            'env' => ['RT05_PANIC_ENABLED' => 'true', 'RT05_PANIC_AUDIENCE' => 'all'],
        ], $tokens));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 200));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 409));
        $this->assertSame(3, Emergencies::misuseCount($a->id));
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'darurat.ditangani')->count());
    }
}
