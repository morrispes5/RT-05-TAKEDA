<?php

namespace Tests\Feature;

use App\Models\TagihanIuran;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommittedData;
use Tests\Support\Concurrent;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Gate M11 "generator rerun/concurrent unique": beberapa proses PHP independen menjalankan
 * pembuatan tagihan periode yang sama bersamaan; total tagihan baru tepat satu per rumah.
 */
class M11ConcurrencyTest extends TestCase
{
    use CommittedData, Concurrent, Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markCommittedData();
    }

    protected function tearDown(): void
    {
        $this->purgeCommittedData();
        parent::tearDown();
    }

    public function test_concurrent_generation_creates_exactly_one_charge_per_house(): void
    {
        $houses = [];
        foreach (['m11a', 'm11b', 'm11c'] as $n) {
            $houses[] = $this->warga($n)->rumah()->id;
        }
        DB::table('rumah')->whereIn('id', $houses)->update(['mulai_tagih' => '2026-10-01']);
        $token = $this->pengurus('m11-race@contoh.test')->createToken('race')->plainTextToken;

        $results = $this->runConcurrently(array_map(fn ($i) => [
            'kind' => 'http', 'method' => 'POST', 'uri' => '/api/v1/admin/dues/generate', 'bearer' => $token,
            'body' => ['periode' => '2026-10'], 'ip' => "10.11.0.$i", 'label' => "m11-$i",
        ], [1, 2, 3, 4]));

        $this->assertSame([200, 200, 200, 200], array_column($results, 'status'));
        $this->assertSame(3, array_sum(array_map(fn ($r) => $r['data']['tagihan_baru'], $results)), 'tagihan baru terbagi antar proses, tidak ganda');
        $this->assertSame(3, TagihanIuran::whereIn('rumah_id', $houses)->where('periode', '2026-10-01')->count());

        // Rerun berurutan setelah balapan: tidak ada tagihan baru.
        $this->withToken($token)->postJson('/api/v1/admin/dues/generate', ['periode' => '2026-10'])->assertOk()->assertJsonPath('data.tagihan_baru', 0);
    }
}
