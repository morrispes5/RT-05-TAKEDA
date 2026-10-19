<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M15: setiap query ke Neon dari Indonesia ±60 ms (diukur di staging), jadi jumlah query per layar
 * adalah anggaran latensi. Request bearer biasa tidak menulis apa pun (last_used_at dimatikan).
 */
class M15PerformanceBudgetTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    public function test_bearer_reads_do_not_write_and_stay_within_query_budget(): void
    {
        $token = $this->warga('52')->createToken('hp')->plainTextToken;
        $budget = ['/api/v1/auth/me' => 6, '/api/v1/agendas' => 3, '/api/v1/announcements' => 3, '/api/v1/notifications' => 4, '/api/v1/dues/my' => 10, '/api/v1/household' => 10];
        foreach ($budget as $uri => $max) {
            $this->app['auth']->forgetGuards();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withToken($token)->getJson($uri)->assertOk();
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
            $writes = array_filter($queries, fn ($q) => preg_match('/^\s*(insert|update|delete)\b/i', $q));
            $this->assertSame([], array_values($writes), "$uri tidak boleh menulis");
            $this->assertLessThanOrEqual($max, count($queries), "$uri memakai ".count($queries).' query');
        }
        $this->assertNull(DB::table('personal_access_tokens')->value('last_used_at'));
    }
}
