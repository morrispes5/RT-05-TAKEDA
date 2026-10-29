<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\FlexibleDues;
use App\Domain\Finance\OldBook;
use App\Domain\Finance\Reconciliation;
use App\Domain\Residents\Address;
use App\Exceptions\DomainConflict;
use App\Models\Rumah;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** ADR49: impor rekap bendahara sebagai lunas buku lama. Berkas sintetis; nama/NIK palsu tidak boleh bocor ke keluaran. */
class M18OldBookImportTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    private const FAKE_NAME = 'Nama Contoh Sintetis';

    private const FAKE_NIK = '9999000011112222';

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 29));
        config(['finance.v2' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** Baris rumah: [blok, nomor, 12 nominal iuran]; kolom mengikuti rekap bendahara (Q=Jan, selang-seling sumbangan). */
    private function workbook(array $houses, array $header = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'], int $totalOffset = 0): string
    {
        $file = sys_get_temp_dir().'/rt05-oldbook-'.Str::uuid().'.xlsx';
        $this->files[] = $file;
        $writer = new Writer;
        $writer->openToFile($file);
        $writer->getCurrentSheet()->setName('IURAN 2026');
        $blank = array_fill(0, 46, '');
        $writer->addRow(Row::fromValues(array_replace($blank, [1 => 'REKAPITULASI IURAN WARGA RT 005 / RW 07 TAHUN 2023'])));
        $writer->addRow(Row::fromValues($blank));
        $writer->addRow(Row::fromValues(array_replace($blank, [1 => 'No.', 2 => 'NIK', 4 => 'NAMA KEPALA KELUARGA', 5 => 'ALAMAT'])));
        $months = $blank;
        foreach ($header as $m => $label) {
            $months[16 + 2 * $m] = $label;
        }
        $writer->addRow(Row::fromValues($months));
        $writer->addRow(Row::fromValues(array_replace($blank, [16 => 'Iuran', 17 => 'Sumbangan'])));
        foreach ($houses as $i => [$block, $number, $dues]) {
            $row = array_replace($blank, [1 => $i + 1, 2 => self::FAKE_NIK, 4 => self::FAKE_NAME, 5 => $block === 'D1' ? 'Jl. Mawar 8 ' : 'Jl. Mawar 12',
                6 => $block, 7 => '/', 8 => $number, 15 => 'Berpenghuni']);
            foreach ($dues as $m => $amount) {
                $row[16 + 2 * $m] = $amount ?: '';
            }
            $row[17] = $i === 0 ? 10000 : '';
            $writer->addRow(Row::fromValues($row));
        }
        $total = array_replace($blank, [5 => 'TOTAL']);
        for ($m = 0; $m < 12; $m++) {
            $total[16 + 2 * $m] = array_sum(array_map(fn ($h) => $h[2][$m], $houses)) + ($m === 0 ? $totalOffset : 0);
        }
        $writer->addRow(Row::fromValues($total));
        $writer->addRow(Row::fromValues($blank));
        $writer->addRow(Row::fromValues(array_replace($blank, [1 => 'KET :'])));
        $writer->addRow(Row::fromValues(array_replace($blank, [4 => ' : KURANG BAYAR'])));
        $writer->close();

        return $file;
    }

    private function decisions(array $data): string
    {
        $file = sys_get_temp_dir().'/rt05-oldbook-'.Str::uuid().'.json';
        $this->files[] = $file;
        file_put_contents($file, json_encode($data));

        return $file;
    }

    private static function months(int $amount, int $count, array $tail = []): array
    {
        return array_slice(array_merge(array_fill(0, $count, $amount), $tail, array_fill(0, 12, 0)), 0, 12);
    }

    /** Enam rumah contoh: lunas s/d Sep, lunas setahun, tarif 35rb, bayar 85rb, kurang bayar Sep, kosong; plus satu dilewati. */
    private function scenario(): array
    {
        return [
            ['D1', 1, self::months(75000, 9)],
            ['D1', 2, self::months(75000, 12)],
            ['D1', 3, self::months(35000, 10)],
            ['D1', 4, self::months(85000, 10)],
            ['D1', 5, self::months(75000, 8, [50000])],
            ['D1', 6, self::months(0, 0)],
            ['D5', '17A', self::months(0, 0)],
        ];
    }

    private function scenarioDecisions(): string
    {
        return $this->decisions(['tarif' => ['D1-3' => ['nominal' => 35000, 'alasan' => 'Rumah kedua, keputusan pengurus']],
            'tarif_umum' => ['D1-4'], 'tidak_ditagih' => ['D1-6']]);
    }

    private function plan(string $file, ?string $decisions = null, string $until = '2026-10'): array
    {
        return OldBook::plan(OldBook::read($file, 'IURAN 2026'), OldBook::decisions($decisions), 2026, $until, OldBook::commonRate(2026));
    }

    private function invariant(): void
    {
        $this->assertSame(0, DB::table('rt05_finance_violations')->count());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    public function test_check_reads_addresses_and_amounts_only_and_writes_nothing(): void
    {
        $file = $this->workbook($this->scenario());
        $before = [DB::table('rumah')->count(), DB::table('tagihan_iuran')->count(), DB::table('tarif_iuran')->count()];

        $exit = Artisan::call('rt05:impor-buku-lama', ['berkas' => $file, '--tahun' => 2026, '--sampai' => '2026-10']);
        $output = Artisan::output();

        $this->assertSame(1, $exit, 'Tanpa keputusan tarif, cek gagal');
        $this->assertStringContainsString('D1-3', $output);
        $this->assertStringContainsString('D1-4', $output);
        $this->assertStringNotContainsString(self::FAKE_NAME, $output);
        $this->assertStringNotContainsString(self::FAKE_NIK, $output);
        $this->assertSame($before, [DB::table('rumah')->count(), DB::table('tagihan_iuran')->count(), DB::table('tarif_iuran')->count()]);

        $plan = $this->plan($file);
        $this->assertCount(2, $plan['perlu_keputusan']);
        $this->assertSame(['D1-3', 'D1-4'], array_column($plan['perlu_keputusan'], 'kode'));
        $this->assertStringNotContainsString(self::FAKE_NAME, json_encode($plan));
        $this->assertStringNotContainsString(self::FAKE_NIK, json_encode($plan));
        $this->assertSame(7, count($plan['rumah']), 'Baris TOTAL dan keterangan warna bukan rumah');
    }

    public function test_review_workbook_is_written_without_names(): void
    {
        $file = $this->workbook($this->scenario());
        $review = sys_get_temp_dir().'/rt05-oldbook-'.Str::uuid().'.xlsx';
        $this->files[] = $review;
        Artisan::call('rt05:impor-buku-lama', ['berkas' => $file, '--tahun' => 2026, '--sampai' => '2026-10',
            '--keputusan' => $this->scenarioDecisions(), '--laporan' => $review]);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($review));
        $xml = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $xml .= $zip->getFromIndex($i);
        }
        $zip->close();
        $this->assertStringContainsString('D1-5', $xml);
        $this->assertStringContainsString('Kurang 25000', $xml);
        $this->assertStringNotContainsString(self::FAKE_NAME, $xml);
        $this->assertStringNotContainsString(self::FAKE_NIK, $xml);
    }

    public function test_apply_settles_old_book_without_touching_cash(): void
    {
        $actor = $this->pengurus();
        $plan = $this->plan($this->workbook($this->scenario()), $this->scenarioDecisions());
        $this->assertSame([], $plan['perlu_keputusan']);

        $result = OldBook::apply($actor, $plan);

        $this->assertSame(6, $result['rumah']);
        $this->assertSame(6, $result['rumah_baru']);
        $this->assertSame(1, $result['rumah_dilewati']);
        $this->assertFalse(Rumah::where('kode_rumah', 'D1-6')->exists());
        $this->assertSame('Mawar 8', Rumah::where('kode_rumah', 'D1-1')->value('jalan'));
        $this->assertSame('2026-01-01', substr((string) Rumah::where('kode_rumah', 'D5-17A')->value('mulai_tagih'), 0, 10));

        // 10 bulan × 5 rumah + D1-2 lunas setahun (12 bulan).
        $this->assertSame(62, DB::table('tagihan_iuran')->count());
        $this->assertSame(0, DB::table('tagihan_iuran')->where('periode', '2026-11-01')->whereNot('rumah_id', Rumah::where('kode_rumah', 'D1-2')->value('id'))->count());
        $this->assertSame(350000, (int) DB::table('tagihan_iuran')->join('rumah', 'rumah.id', '=', 'tagihan_iuran.rumah_id')
            ->where('kode_rumah', 'D1-3')->sum('nominal_tagihan'));

        $this->assertSame(9 * 75000 + 12 * 75000 + 10 * 35000 + 10 * 75000 + 8 * 75000 + 50000, (int) DB::table('rt05_tagihan_saldo')->sum('terbayar_buku_lama'));
        $this->assertSame(75000 + 25000 + 75000 + 10 * 75000, (int) DB::table('rt05_tagihan_saldo')->sum('sisa'));
        $this->assertSame(100000, $result['nominal_lebih'], 'Kelebihan 85rb hanya dilaporkan');
        $this->assertSame(0, DB::table('transaksi_kas')->count());
        $this->assertSame(0, DB::table('pembayaran_iuran')->count());
        $this->assertSame(0, Dues::balance());

        $charges = collect(FinanceReports::charges())->keyBy(fn ($c) => $c['kode_rumah'].'|'.$c['periode']);
        $this->assertSame('Lunas (buku lama)', $charges['D1-2|2026-12']['status_label']);
        $this->assertSame('Kurang bayar', $charges['D1-5|2026-09']['status_label']);
        $this->assertSame(50000, $charges['D1-5|2026-09']['terbayar']);
        $this->assertSame('Belum dibayar', $charges['D5-17A|2026-01']['status_label']);
        $this->assertSame('Belum dibayar', $charges['D1-1|2026-10']['status_label']);

        $audit = DB::table('audit_logs')->where('action', 'keuangan.impor_buku_lama')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString(self::FAKE_NAME, (string) $audit->safe_diff);
        $this->invariant();

        $this->expectException(DomainConflict::class);
        OldBook::apply($actor, $plan);
    }

    public function test_apply_through_command_requires_reviewed_fingerprint(): void
    {
        $this->pengurus('bendahara@contoh.test');
        $file = $this->workbook($this->scenario());
        $decisions = $this->scenarioDecisions();
        $args = ['berkas' => $file, '--tahun' => 2026, '--sampai' => '2026-10', '--keputusan' => $decisions,
            '--terapkan' => true, '--pengurus' => 'bendahara@contoh.test'];

        $this->assertSame(1, Artisan::call('rt05:impor-buku-lama', $args + ['--sidik-jari' => str_repeat('0', 64)]));
        $this->assertSame(0, DB::table('impor_keuangan')->count());

        $fingerprint = $this->plan($file, $decisions)['sidik_jari'];
        $this->assertSame(0, Artisan::call('rt05:impor-buku-lama', $args + ['--sidik-jari' => $fingerprint]));
        $this->assertSame(1, DB::table('impor_keuangan')->count());
        $this->assertSame($fingerprint, DB::table('impor_keuangan')->value('sidik_jari'));
        $this->invariant();
    }

    public function test_existing_house_is_reused_and_existing_charge_adjusted_to_special_rate(): void
    {
        $actor = $this->pengurus();
        $house = Rumah::create(['kode_rumah' => 'D1-3', 'blok' => 'D1', 'jalan' => 'Mawar 8', 'nomor' => '3',
            'alamat_key' => Address::key('D1', 'Mawar 8', '3'), 'tagihan_aktif' => true, 'mulai_tagih' => '2026-10-01']);
        Dues::generate(Dues::period('2026-10'));
        $this->assertSame(75000, (int) DB::table('tagihan_iuran')->where('rumah_id', $house->id)->value('nominal_tagihan'));

        OldBook::apply($actor, $this->plan($this->workbook($this->scenario()), $this->scenarioDecisions()));

        $house->refresh();
        $this->assertSame('2026-01', $house->mulai_tagih->format('Y-m'));
        $this->assertSame(2, $house->versi);
        $oct = DB::table('rt05_tagihan_saldo')->where('rumah_id', $house->id)->where('periode', '2026-10-01')->first();
        $this->assertSame(75000, (int) $oct->nominal_tagihan, 'Snapshot lama tidak diubah');
        $this->assertSame(35000, (int) $oct->nominal_efektif);
        $this->assertSame(0, (int) $oct->sisa);
        $this->assertSame(-40000, (int) DB::table('penyesuaian_tagihan')->where('tagihan_id', $oct->id)->value('delta'));
        $this->assertSame(1, DB::table('rumah')->where('kode_rumah', 'D1-3')->count());
        $this->invariant();
    }

    public function test_conflict_with_recorded_payment_rolls_back_everything(): void
    {
        $actor = $this->pengurus();
        $house = Rumah::create(['kode_rumah' => 'D1-1', 'blok' => 'D1', 'jalan' => 'Mawar 8', 'nomor' => '1',
            'alamat_key' => Address::key('D1', 'Mawar 8', '1'), 'tagihan_aktif' => true, 'mulai_tagih' => '2026-10-01']);
        Dues::generate(Dues::period('2026-10'));
        FlexibleDues::record($actor, $house->id, ['2026-10'], 75000, '2026-10-20', null, null, 'Bayar tunai di pos',
            FlexibleDues::preview($house->id, ['2026-10'])['fingerprint']);
        $before = [DB::table('rumah')->count(), DB::table('tagihan_iuran')->count(), DB::table('tarif_iuran')->count(), DB::table('tarif_rumah')->count()];

        $plan = $this->plan($this->workbook([['D1', 1, self::months(75000, 10)], ['D1', 3, self::months(35000, 10)]]),
            $this->decisions(['tarif' => ['D1-3' => ['nominal' => 35000, 'alasan' => 'Rumah kedua, keputusan pengurus']]]));
        try {
            OldBook::apply($actor, $plan);
            $this->fail('Bentrok pembayaran harus ditolak');
        } catch (DomainConflict $e) {
            $this->assertSame('IMPORT_CONFLICT', $e->errorCode);
        }
        $this->assertSame($before, [DB::table('rumah')->count(), DB::table('tagihan_iuran')->count(), DB::table('tarif_iuran')->count(), DB::table('tarif_rumah')->count()]);
        $this->assertSame(0, DB::table('impor_keuangan')->count());
        $this->assertSame(75000, Dues::balance());
        $this->invariant();
    }

    public function test_houses_outside_rekap_with_early_billing_start_block_backfill(): void
    {
        $actor = $this->pengurus();
        Rumah::create(['kode_rumah' => 'Z-9', 'blok' => 'Z', 'jalan' => 'Jl. Uji', 'nomor' => '9',
            'alamat_key' => Address::key('Z', 'Jl. Uji', '9'), 'tagihan_aktif' => true, 'mulai_tagih' => '2026-03-01']);
        $plan = $this->plan($this->workbook([['D1', 1, self::months(75000, 10)]]));

        try {
            OldBook::apply($actor, $plan);
            $this->fail('Rumah di luar rekap tidak boleh tertagih mundur diam-diam');
        } catch (DomainConflict $e) {
            $this->assertSame('IMPORT_BACKFILL_RISK', $e->errorCode);
            $this->assertStringContainsString('Z-9', $e->getMessage());
        }
        $this->assertSame(0, DB::table('tagihan_iuran')->count());
    }

    public function test_cancel_reopens_charge_and_new_cash_payment_is_counted_once(): void
    {
        $actor = $this->pengurus();
        OldBook::apply($actor, $this->plan($this->workbook([['D1', 1, self::months(75000, 10)]])));
        $house = Rumah::where('kode_rumah', 'D1-1')->value('id');
        $jan = DB::table('rt05_tagihan_saldo')->where('rumah_id', $house)->where('periode', '2026-01-01')->first();
        $this->assertNotNull($jan->buku_lama_id);

        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $url = "/api/v1/admin/old-book-settlements/{$jan->buku_lama_id}/cancel";
        $this->as($actor)->postJson($url, ['alasan' => 'Salah catat', 'versi_keuangan' => 2], $headers)->assertOk();
        $this->as($actor)->postJson($url, ['alasan' => 'Salah catat', 'versi_keuangan' => 2], $headers)->assertOk();
        $this->as($actor)->postJson($url, ['alasan' => 'Salah catat', 'versi_keuangan' => 2], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();

        $jan = DB::table('rt05_tagihan_saldo')->where('id', $jan->id)->first();
        $this->assertSame(75000, (int) $jan->sisa);
        $this->assertSame('belum_bayar', $jan->status);
        $this->assertNull($jan->buku_lama_id);

        FlexibleDues::record($actor, $house, ['2026-01'], 75000, '2026-10-29', null, null, 'Ternyata baru bayar',
            FlexibleDues::preview($house, ['2026-01'])['fingerprint']);
        $this->assertSame(75000, Dues::balance());
        $this->assertSame(0, (int) DB::table('rt05_tagihan_saldo')->where('id', $jan->id)->value('sisa'));
        $this->invariant();
    }

    public function test_house_dues_api_shows_old_book_fields(): void
    {
        $actor = $this->pengurus();
        OldBook::apply($actor, $this->plan($this->workbook([['D1', 2, self::months(75000, 12)]])));
        $house = Rumah::where('kode_rumah', 'D1-2')->value('id');

        $dec = collect($this->as($actor)->getJson("/api/v1/admin/houses/$house/dues")->assertOk()->json('data.tagihan'))->firstWhere('periode', '2026-12');
        $this->assertSame('Lunas (buku lama)', $dec['status_label']);
        $this->assertSame('lunas', $dec['status_pelunasan']);
        $this->assertSame(75000, $dec['terbayar']);
        $this->assertSame(75000, $dec['terbayar_buku_lama']);
        $this->assertNotNull($dec['buku_lama_id']);
    }

    public function test_guards_v2_flag_actor_header_and_decision_file(): void
    {
        $file = $this->workbook([['D1', 1, self::months(75000, 10)]]);
        $plan = $this->plan($file);

        config(['finance.v2' => false]);
        try {
            OldBook::apply($this->pengurus(), $plan);
            $this->fail('Flag v2 mati harus menolak');
        } catch (DomainConflict $e) {
            $this->assertSame('FINANCE_V2_REQUIRED', $e->errorCode);
        }
        config(['finance.v2' => true]);
        try {
            OldBook::apply($this->warga('77'), $plan);
            $this->fail('Warga tidak boleh mengimpor');
        } catch (DomainConflict $e) {
            $this->assertSame('IMPORT_ACTOR_INVALID', $e->errorCode);
        }
        try {
            OldBook::apply($this->pengurus('lain@contoh.test'), $this->plan($file, null, '2026-12'));
            $this->fail('Batas impor di masa depan memunculkan tunggakan yang belum jatuh tempo');
        } catch (DomainConflict $e) {
            $this->assertSame('IMPORT_PERIOD_INVALID', $e->errorCode);
        }
        $this->assertFalse(Rumah::where('kode_rumah', 'D1-1')->exists());
        $this->assertSame(0, DB::table('impor_keuangan')->count());

        try {
            OldBook::read($this->workbook([['D1', 1, self::months(75000, 10)]], ['JAN', 'MAR']), 'IURAN 2026');
            $this->fail('Header bulan salah harus ditolak');
        } catch (DomainConflict $e) {
            $this->assertSame('IMPORT_HEADER_INVALID', $e->errorCode);
        }
        try {
            $this->plan($file, $this->decisions(['tarif' => ['D9-9' => ['nominal' => 50000, 'alasan' => 'Tidak ada di sheet']]]));
        } catch (DomainConflict) {
            $this->fail('Kode tak dikenal dilaporkan sebagai perlu keputusan, bukan pengecualian');
        }
        $this->assertSame('D9-9', $this->plan($file, $this->decisions(['tarif' => ['D9-9' => ['nominal' => 50000, 'alasan' => 'Tidak ada di sheet']]]))['perlu_keputusan'][0]['kode']);
        $this->expectException(DomainConflict::class);
        OldBook::decisions($this->decisions(['tarif_umum' => ['D1-1'], 'tidak_ditagih' => ['D1-1']]));
    }

    public function test_total_row_mismatch_blocks_import(): void
    {
        $plan = $this->plan($this->workbook([['D1', 1, self::months(75000, 10)]], totalOffset: 75000));

        $this->assertCount(1, $plan['perlu_keputusan']);
        $this->assertStringContainsString('2026-01', $plan['perlu_keputusan'][0]['pesan']);
        $this->assertStringContainsString('TOTAL', $plan['perlu_keputusan'][0]['pesan']);
    }

    public function test_total_row_above_ten_million_matches_like_a_real_rekap(): void
    {
        $houses = array_map(fn ($n) => ['D'.(1 + intdiv($n, 30)), $n % 30 + 1, self::months(75000, 10)], range(0, 149));
        $plan = $this->plan($this->workbook($houses));

        $this->assertSame([], $plan['perlu_keputusan']);
        $this->assertSame(150, $plan['ringkasan']['rumah']);
        $this->assertSame(150 * 10 * 75000, $plan['ringkasan']['nominal_buku_lama']);
    }

    public function test_common_rate_must_be_strict_majority_otherwise_decision_needed(): void
    {
        $file = $this->workbook([
            ['D1', 1, self::months(75000, 6, [85000, 85000, 85000, 85000])],
            ['D1', 2, self::months(75000, 5, [85000, 85000, 85000, 85000, 85000])],
        ]);
        $plan = $this->plan($file);

        $this->assertSame(['D1-2'], array_column($plan['perlu_keputusan'], 'kode'));
        $this->assertCount(4 + 5, $plan['catatan']);
        $this->assertSame('lebih', $plan['catatan'][0]['jenis']);
    }
}
