<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\FinanceWorkbook;
use App\Domain\Finance\FlexibleDues;
use App\Models\TarifIuran;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;
use ZipArchive;

class M18ReportsTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    private function crossYear(): array
    {
        config(['finance.v2' => true]);
        $this->travelTo(CarbonImmutable::parse('2025-12-31 10:00:00', 'Asia/Jakarta'));
        TarifIuran::create(['nominal' => 75000, 'mulai_periode' => '2025-12-01', 'alasan' => 'Tarif sintetis sebelum pergantian tahun']);
        $resident = $this->warga();
        $actor = $this->pengurus();
        $house = $resident->rumah()->id;
        DB::table('rumah')->where('id', $house)->update(['mulai_tagih' => '2025-12-01']);
        Dues::generate(Dues::period('2026-01'));
        Dues::recordCash($actor, 'saldo_awal', 500000, '2025-12-01', 'Modal awal', 'Modal awal sintetis');
        $payment = FlexibleDues::record($actor, $house, ['2026-01'], 100000, '2025-12-31', null, 'titipan', null,
            FlexibleDues::preview($house, ['2026-01'])['fingerprint']);
        $this->travelTo(CarbonImmutable::parse('2026-01-02 10:00:00', 'Asia/Jakarta'));
        FlexibleDues::reverse($actor, $payment->id, 'Koreksi penerimaan beda tahun');
        FlexibleDues::record($actor, $house, ['2026-01'], 50000, '2026-01-02', 'keringanan', null, 'Keringanan setelah verifikasi',
            FlexibleDues::preview($house, ['2026-01'])['fingerprint']);
        $this->as($actor);

        return [$resident, $actor, $house];
    }

    private function readWorkbook(string $file): array
    {
        $reader = new Reader;
        $reader->open($file);
        $sheets = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                }
                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    public function test_cash_year_follows_posting_and_charge_settlement_is_current(): void
    {
        $this->crossYear();
        $old = FinanceReports::year(2025);
        $this->assertSame(600000, $old['total']['saldo_akhir']);
        $this->assertSame(100000, $old['total']['pemasukan']);
        $response = $this->getJson('/api/v1/admin/finance-reports?tahun=2026')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $report = $response->json('data');
        $this->assertSame([], OpenApi::errors($response->json(), 'FinanceReportEnvelope'));
        $this->assertSame(600000, $report['total']['saldo_awal']);
        $this->assertSame(50000, $report['total']['pemasukan']);
        $this->assertSame(-100000, $report['total']['koreksi']);
        $this->assertSame(550000, $report['total']['saldo_akhir']);
        $this->assertSame(-25000, $report['total']['titipan_diterima']);
        $this->assertSame(50000, $report['tagihan']['terbayar']);
        $this->assertSame(25000, $report['tagihan']['keringanan']);
        $this->assertSame(0, $report['tagihan']['sisa']);
        $this->assertCount(12, $report['bulan']);
        foreach ($report['bulan'] as $month) {
            $c = $month['kas'];
            $this->assertSame($c['saldo_awal'] + $c['modal_awal'] + $c['pemasukan'] - $c['pengeluaran'] + $c['koreksi'], $c['saldo_akhir']);
        }
        $monthly = $this->getJson('/api/v1/admin/finance-reports?periode=2026-01')->assertOk();
        $this->assertSame($report['bulan'][0]['kas'], $monthly->json('data.kas'));
        $this->assertSame([], OpenApi::errors($monthly->json(), 'FinanceReportEnvelope'));
    }

    public function test_json_csv_and_fourteen_excel_sheets_have_identical_monthly_values(): void
    {
        $this->crossYear();
        $report = FinanceReports::year(2026);
        $csv = $this->get('/api/v1/admin/exports/keuangan.csv?tahun=2026')->assertOk()->streamedContent();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        $this->assertSame(FinanceReports::SUMMARY_HEADER, array_shift($rows));
        $this->assertSame(array_map(fn ($row) => array_map('strval', $row), FinanceReports::summaryRows($report)), $rows);

        $response = $this->get('/api/v1/admin/exports/keuangan.xlsx?tahun=2026')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $file = $response->baseResponse->getFile()->getPathname();
        $this->beforeApplicationDestroyed(function () use ($file) {
            if (is_file($file)) {
                unlink($file);
            }
        });
        $this->assertStringStartsWith(storage_path('app/private/finance'), $file);
        $sheets = $this->readWorkbook($file);
        $this->assertCount(14, $sheets);
        $this->assertSame(['Rekap Tahun', 'Rumah x Bulan', '01 Januari', '02 Februari', '03 Maret', '04 April', '05 Mei', '06 Juni', '07 Juli', '08 Agustus', '09 September', '10 Oktober', '11 November', '12 Desember'], array_keys($sheets));
        $this->assertSame(FinanceReports::SUMMARY_HEADER, $sheets['Rekap Tahun'][3]);
        foreach (FinanceReports::summaryRows($report) as $index => $row) {
            $this->assertSame($row, $sheets['Rekap Tahun'][$index + 4]);
        }
        $matrix = $sheets['Rumah x Bulan'][4];
        $this->assertSame([75000, 50000, 25000, 0, 'Lunas dengan keringanan'], array_slice($matrix, 2, 5));
        $this->assertSame('Tidak ditagih', $matrix[11]);
        $this->assertTrue(DB::table('audit_logs')->where('action', 'ekspor.xlsx')->exists());
        ob_start();
        $response->baseResponse->sendContent();
        ob_end_clean();
        $this->assertFileDoesNotExist($file, 'Private XLSX must be deleted after response');
    }

    public function test_excel_user_text_is_literal_and_has_freeze_filter_and_currency_format(): void
    {
        [, $actor] = $this->crossYear();
        $texts = ['=HYPERLINK("https://example.test")', '+SUM(1,2)', '-cmd', '@SUM(1)', "\t=1+1", "\r=1+1"];
        foreach ($texts as $text) {
            Dues::recordCash($actor, 'pemasukan', 1000, '2026-01-02', 'Uji formula', $text);
        }
        $report = FinanceReports::year(2026);
        $file = storage_path('app/private/formula-'.uniqid().'.xlsx');
        try {
            FinanceWorkbook::write($report, $file);
            $rows = $this->readWorkbook($file)['01 Januari'];
            $values = array_map(fn ($v) => is_string($v) ? preg_replace('/\r\n?/', "\n", $v) : $v, array_merge(...$rows));
            foreach ($texts as $text) {
                $this->assertContains(preg_replace('/\r\n?/', "\n", $text), $values);
            }
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($file));
            for ($i = 1; $i <= 14; $i++) {
                $xml = $zip->getFromName("xl/worksheets/sheet$i.xml");
                $this->assertDoesNotMatchRegularExpression('/<f(?:[\s>\/])/', $xml);
                $this->assertStringContainsString('<autoFilter', $xml);
                $this->assertStringContainsString('ySplit="4"', $xml);
            }
            $styles = $zip->getFromName('xl/styles.xml');
            $this->assertStringContainsString('183B50', $styles);
            $this->assertStringContainsString('FFD84D', $styles);
            $this->assertStringContainsString('Rp', $styles);
            $zip->close();
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_report_permissions_and_parameter_validation(): void
    {
        [$resident, $actor] = $this->crossYear();
        $this->as($resident);
        foreach (['finance-reports?tahun=2026', 'exports/keuangan.csv?tahun=2026', 'exports/keuangan.xlsx?tahun=2026'] as $path) {
            $this->getJson('/api/v1/admin/'.$path)->assertForbidden();
        }
        $this->as($actor)->getJson('/api/v1/admin/finance-reports?tahun=2026&periode=2026-01')->assertStatus(422);
        $this->getJson('/api/v1/admin/exports/keuangan.xlsx?tahun=../2026')->assertStatus(422);
        $this->getJson('/api/v1/admin/exports/keuangan.xlsx')->assertStatus(422);
        config(['finance.v2' => false]);
        $this->getJson('/api/v1/admin/finance-reports?tahun=2026')->assertNotFound();
    }

    public function test_existing_finance_csv_uses_effective_settlement_and_signed_cash_history(): void
    {
        $this->crossYear();
        $csv = $this->get('/api/v1/admin/exports/iuran?periode=2026-01')->assertOk()->streamedContent();
        $lines = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim(substr($csv, 3))));
        $this->assertSame(['kode_rumah', 'periode', 'nominal_snapshot', 'nominal_efektif', 'terbayar', 'dari_titipan', 'keringanan', 'sisa', 'status'], $lines[0]);
        $this->assertSame(['75000', '75000', '50000', '0', '25000', '0', 'Lunas dengan keringanan'], array_slice($lines[1], 2));
        $csv = $this->get('/api/v1/admin/exports/kas?periode=2026-01')->assertOk()->streamedContent();
        $lines = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim(substr($csv, 3))));
        array_shift($lines);
        $this->assertSame(-50000, array_sum(array_map(fn ($row) => (int) $row[4], $lines)));
    }

    public function test_deposit_use_reversal_is_in_its_actual_month_and_does_not_change_cash(): void
    {
        config(['finance.v2' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00:00', 'Asia/Jakarta'));
        $house = $this->warga()->rumah()->id;
        $actor = $this->pengurus();
        FlexibleDues::record($actor, $house, ['2026-10'], 100000, '2026-10-26', null, 'titipan', null,
            FlexibleDues::preview($house, ['2026-10'])['fingerprint']);
        Dues::generate(Dues::period('2026-11'));
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));
        $usage = FlexibleDues::useDeposit($actor, $house, ['2026-11'], 25000, '2026-11-01', FlexibleDues::preview($house, ['2026-11'])['fingerprint']);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00:00', 'Asia/Jakarta'));
        FlexibleDues::reverseDeposit($actor, $usage, 'Koreksi pemakaian bulan berikutnya');
        DB::table('rumah')->where('id', $house)->update(['archived_at' => now()]);
        $report = FinanceReports::year(2026);
        $this->assertSame(25000, $report['bulan'][10]['titipan_dipakai']);
        $this->assertSame(-25000, $report['bulan'][11]['titipan_dipakai']);
        $this->assertSame(100000, $report['total']['saldo_akhir']);
        $this->assertTrue($report['bulan'][10]['rumah'][0]['rumah_arsip']);
        $empty = FinanceReports::year(2027);
        $this->assertSame(100000, $empty['total']['saldo_awal']);
        $this->assertSame(100000, $empty['total']['saldo_akhir']);
        $this->assertSame(0, $empty['tagihan']['nominal_efektif']);
        $file = storage_path('app/private/empty-'.uniqid().'.xlsx');
        try {
            FinanceWorkbook::write($empty, $file);
            $this->assertCount(14, $this->readWorkbook($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_private_export_cleanup_only_removes_expired_generated_files(): void
    {
        $root = storage_path('app/private/finance');
        if (! is_dir($root)) {
            mkdir($root, 0700, true);
        }
        $old = $root.'/'.Str::uuid().'.xlsx';
        $fresh = $root.'/'.Str::uuid().'.xlsx';
        $other = $root.'/notes-'.uniqid().'.txt';
        foreach ([$old, $fresh, $other] as $file) {
            file_put_contents($file, 'Synthetic export cleanup fixture');
        }
        touch($old, now()->subDays(2)->getTimestamp());
        touch($other, now()->subDays(2)->getTimestamp());
        try {
            $this->artisan('rt05:bersihkan-ekspor')->assertSuccessful();
            $this->assertFileDoesNotExist($old);
            $this->assertFileExists($fresh);
            $this->assertFileExists($other);
        } finally {
            foreach ([$old, $fresh, $other] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
