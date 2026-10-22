<?php

namespace Tests\Feature;

use App\Models\TagihanIuran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * UAT M15 (19–20 Okt 2026): tagihan rumah baru langsung terlihat warga, blok wajib untuk rumah baru,
 * dan ekspor CSV pembayaran/rekap bulan tersedia untuk pengurus.
 */
class M15UatFinanceTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    public function test_new_registration_and_new_house_get_current_month_charge_immediately(): void
    {
        $w = $this->warga('71');
        $bulan = now('Asia/Jakarta')->format('Y-m');
        $this->assertSame(1, TagihanIuran::where('rumah_id', $w->rumah()->id)->where('periode', $bulan.'-01')->count());
        $this->as($w)->getJson('/api/v1/dues/my')->assertOk()->assertJsonPath('data.tagihan.0.periode', $bulan);

        $admin = $this->pengurus('m15-rumah@contoh.test');
        $id = $this->as($admin)->postJson('/api/v1/admin/houses', ['blok' => 'Q', 'jalan' => 'Jl. Uji', 'nomor' => '9', 'mulai_tagih' => $bulan])
            ->assertCreated()->json('data.id');
        $this->assertSame(1, TagihanIuran::where('rumah_id', $id)->count());
    }

    public function test_blok_is_required_for_new_house_and_primary_registration(): void
    {
        $admin = $this->pengurus('m15-blok@contoh.test');
        $this->as($admin)->postJson('/api/v1/admin/houses', ['jalan' => 'Jl. Uji', 'nomor' => '10'])
            ->assertStatus(422)->assertJsonPath('error.fields.blok.0', fn ($m) => is_string($m));
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/register', [
            'registration_token' => $this->freshToken(), 'email' => 'm15blok@contoh.test', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'nama_kepala' => 'Uji Blok', 'jalan' => 'Jl. Uji', 'nomor' => '11', 'jenis_hunian' => 'pemilik', 'device_name' => 'hp',
        ])->assertStatus(422)->assertJsonPath('error.fields.blok.0', fn ($m) => is_string($m));
    }

    public function test_proposal_pdf_over_one_megabyte_is_rejected(): void
    {
        Storage::fake('local');
        $w = $this->warga('73');
        $pdf = UploadedFile::fake()->createWithContent('acara.pdf', "%PDF-1.4\n".str_repeat('0', 1024 * 1024 + 10));
        $this->as($w)->post('/api/v1/activity-proposals', [
            'jenis' => 'olahraga', 'nama' => 'Badminton warga RT', 'deskripsi' => 'Badminton bersama warga RT 05.',
            'lokasi' => 'Lapangan RT 05', 'mulai_at' => now()->addDays(5)->toIso8601String(), 'selesai_at' => now()->addDays(5)->addHours(2)->toIso8601String(),
            'pernyataan' => '1', 'dokumen' => [$pdf],
        ], ['Accept' => 'application/json'])->assertStatus(413)->assertJsonPath('error.code', 'PAYLOAD_TOO_LARGE');
    }

    public function test_payments_and_monthly_recap_export_as_csv(): void
    {
        $w = $this->warga('72');
        $admin = $this->pengurus('m15-ekspor@contoh.test');
        $bulan = now('Asia/Jakarta')->format('Y-m');

        $csv = $this->as($admin)->get('/api/v1/admin/exports/pembayaran')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFnomor_bukti,kode_rumah,tanggal_bayar,periode,total_bayar,status", $csv);

        $rekap = $this->as($admin)->get('/api/v1/admin/exports/iuran?periode='.$bulan)->assertOk()->streamedContent();
        $this->assertStringContainsString($w->rumah()->kode_rumah.','.$bulan.',', $rekap);
        $this->as($admin)->getJson('/api/v1/admin/exports/iuran?periode=bulan-ini')->assertStatus(422);
        $this->as($w)->get('/api/v1/admin/exports/pembayaran', ['Accept' => 'application/json'])->assertForbidden();
    }

    /** Audit M16: ekspor Kas dan Pembayaran per bulan hanya memuat transaksi pada bulan itu. */
    public function test_cash_and_payment_exports_respect_the_selected_month(): void
    {
        $w = $this->warga('74');
        $rumah = $w->rumah();
        $admin = $this->pengurus('m16-ekspor@contoh.test');
        $ini = now('Asia/Jakarta');
        $lalu = $ini->copy()->startOfMonth()->subMonth();
        $this->as($admin);
        $cash = fn (string $tanggal, string $ket) => $this->postJson('/api/v1/admin/cash-transactions', [
            'jenis' => 'pemasukan', 'nominal' => 10000, 'tanggal' => $tanggal, 'kategori' => 'Operasional', 'keterangan' => $ket,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $cash($lalu->toDateString(), 'Kas bulan lalu sintetis');
        $cash($ini->toDateString(), 'Kas bulan ini sintetis');
        $bayar = fn (string $tanggal) => $this->postJson('/api/v1/admin/dues-payments', [
            'rumah_id' => $rumah->id, 'periode' => [$ini->format('Y-m')], 'nominal' => 75000, 'tanggal_bayar' => $tanggal,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.nomor_bukti');
        // Dibayar di bulan lalu (tanggal bayar), untuk tagihan bulan ini: ekspor pembayaran mengikuti tanggal bayar.
        $nomor = $bayar($lalu->toDateString());

        $kasLalu = $this->get('/api/v1/admin/exports/kas?periode='.$lalu->format('Y-m'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Kas bulan lalu sintetis', $kasLalu);
        $this->assertStringNotContainsString('Kas bulan ini sintetis', $kasLalu);
        $kasSemua = $this->get('/api/v1/admin/exports/kas')->assertOk()->streamedContent();
        $this->assertStringContainsString('Kas bulan ini sintetis', $kasSemua);

        $this->assertStringContainsString($nomor, $this->get('/api/v1/admin/exports/pembayaran?periode='.$lalu->format('Y-m'))->streamedContent());
        $this->assertStringNotContainsString($nomor, $this->get('/api/v1/admin/exports/pembayaran?periode='.$ini->format('Y-m'))->streamedContent());
        $this->getJson('/api/v1/admin/exports/kas?periode=2026-13')->assertStatus(422);
    }
}
