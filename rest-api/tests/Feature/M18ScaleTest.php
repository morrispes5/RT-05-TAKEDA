<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\FlexibleDues;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Synthetic RT-size data; integrity, batched reporting, and measured database validation cost. */
class M18ScaleTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    public function test_three_hundred_houses_twelve_months_validate_and_report_without_per_house_queries(): void
    {
        config(['finance.v2' => true]);
        $actor = $this->pengurus();
        $houses = [];
        for ($i = 1; $i <= 300; $i++) {
            $houses[] = ['id' => (string) Str::uuid(), 'kode_rumah' => "UJI-$i", 'jalan' => 'Jalan sintetis', 'nomor' => (string) $i,
                'alamat_key' => "scale|uji|$i", 'tagihan_aktif' => true, 'mulai_tagih' => '2026-10-01'];
        }
        DB::table('rumah')->insert($houses);
        for ($m = 0; $m < 12; $m++) {
            $this->assertSame(300, Dues::generate(Dues::period('2026-10')->addMonths($m)));
        }
        $started = hrtime(true);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $validationMs = (int) ((hrtime(true) - $started) / 1000000);
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        FlexibleDues::record($actor, $houses[0]['id'], ['2026-10'], 100000, '2026-10-26', null, 'titipan', null,
            FlexibleDues::preview($houses[0]['id'], ['2026-10'])['fingerprint']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $report = FinanceReports::year(2026);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(10, $queries);
        $this->assertCount(300, $report['bulan'][9]['rumah']);
        $this->assertSame(67500000, $report['tagihan']['nominal_efektif']);
        $this->assertSame(100000, $report['total']['saldo_akhir']);
        $this->assertSame(0, DB::table('rt05_finance_violations')->count());
        fwrite(STDERR, "M18_SCALE houses=300 charges=3600 validation_ms=$validationMs report_queries=$queries\n");
    }
}
