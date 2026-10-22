<?php

namespace Tests\Feature;

use App\Domain\Community\Notifications;
use App\Domain\Identity\RegistrationToken;
use App\Models\OutboxEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Audit keamanan M16: token registrasi enam digit tidak bisa ditebak dengan banyak IP, dan pengurus diberi tahu setiap
 * akun utama baru atau token yang diganti otomatis.
 */
class M16AuditFixesTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function inboxTitles(string $akunId): array
    {
        return DB::table('notifikasi')->where('penerima_id', $akunId)->pluck('judul')->all();
    }

    private function handle(string $type): void
    {
        OutboxEvent::where('type', $type)->whereNull('completed_at')->get()->each(fn (OutboxEvent $e) => Notifications::handle($e));
    }

    public function test_token_is_rotated_after_too_many_wrong_guesses_from_many_ips_and_pengurus_is_told(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = $this->pengurus('m16-token@contoh.test');
        $code = $this->freshToken();
        $wrong = $code === '000000' ? '111111' : '000000';
        $first = RegistrationToken::active()->id;
        $body = fn (string $token) => [
            'email' => 'm16token@contoh.test', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'registration_token' => $token, 'device_name' => 'hp', 'nama_kepala' => 'Uji Token', 'blok' => 'T',
            'jalan' => 'Jl. Token', 'nomor' => '16', 'jenis_hunian' => 'pemilik',
        ];

        for ($i = 1; $i < RegistrationToken::MAX_FAILURES; $i++) {
            // Setiap tebakan dari IP berbeda: batas per IP tidak pernah tercapai.
            $this->withServerVariables(['REMOTE_ADDR' => "10.16.0.$i"])->postJson('/api/v1/auth/register', $body($wrong))
                ->assertStatus(422)->assertJsonPath('error.code', 'REGISTRATION_TOKEN_INVALID');
        }
        $this->assertSame($first, RegistrationToken::active()->id, 'belum mencapai batas');
        $this->assertSame(RegistrationToken::MAX_FAILURES - 1, (int) DB::table('token_registrasi')->where('id', $first)->value('percobaan_gagal'));

        $this->withServerVariables(['REMOTE_ADDR' => '10.16.1.1'])->postJson('/api/v1/auth/register', $body($wrong))->assertStatus(422);
        $baru = RegistrationToken::active();
        $this->assertNotSame($first, $baru->id, 'token diganti otomatis');
        $this->assertSame(0, (int) $baru->percobaan_gagal);
        // Token lama yang mungkin sudah ditebak tidak lagi berlaku; jawaban tetap generik.
        $this->withServerVariables(['REMOTE_ADDR' => '10.16.1.2'])->postJson('/api/v1/auth/register', $body($code))
            ->assertStatus(422)->assertJsonPath('error.code', 'REGISTRATION_TOKEN_INVALID');

        $this->handle('token.dirotasi_otomatis');
        $this->assertContains('Token registrasi diganti', $this->inboxTitles($admin->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'token_registrasi.dirotasi', 'entity_id' => $baru->id]);
    }

    public function test_new_primary_account_notifies_pengurus(): void
    {
        $admin = $this->pengurus('m16-utama@contoh.test');
        $warga = $this->warga('161');
        $this->handle('akun.registrasi_utama');
        $this->assertContains('Akun utama baru', $this->inboxTitles($admin->id));
        $this->assertNotContains('Akun utama baru', $this->inboxTitles($warga->id), 'warga lain tidak diberi tahu');
    }

    /** Audit M16: nominal/rumah/nomor bukti tidak bisa diubah langsung di database; pembalikan resmi tetap jalan. */
    public function test_billing_rows_are_locked_except_official_reversal(): void
    {
        $w = $this->warga('162');
        $rumah = $w->rumah();
        $admin = $this->pengurus('m16-kunci@contoh.test');
        $bulan = now('Asia/Jakarta')->format('Y-m');
        $id = $this->as($admin)->postJson('/api/v1/admin/dues-payments', [
            'rumah_id' => $rumah->id, 'periode' => [$bulan], 'nominal' => 75000, 'tanggal_bayar' => now('Asia/Jakarta')->toDateString(),
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $tagihan = DB::table('tagihan_iuran')->where('rumah_id', $rumah->id)->where('periode', $bulan.'-01')->value('id');

        $blocked = function (callable $write, string $label) {
            try {
                DB::transaction($write);
                $this->fail("$label seharusnya ditolak database");
            } catch (QueryException $e) {
                $this->assertStringContainsString('42501', $e->getCode().' '.$e->getMessage(), $label);
            }
        };
        $blocked(fn () => DB::table('tagihan_iuran')->where('id', $tagihan)->update(['nominal_tagihan' => 1]), 'ubah nominal tagihan');
        $blocked(fn () => DB::table('pembayaran_iuran')->where('id', $id)->update(['total_bayar' => 1]), 'ubah total bayar');
        $blocked(fn () => DB::table('pembayaran_iuran')->where('id', $id)->delete(), 'hapus pembayaran');
        $blocked(fn () => DB::table('alokasi_pembayaran')->where('pembayaran_id', $id)->delete(), 'hapus alokasi');
        $blocked(fn () => DB::table('alokasi_pembayaran')->where('pembayaran_id', $id)->update(['nominal_alokasi' => 1]), 'ubah alokasi');

        // Jalur resmi: pembalikan oleh pengurus tetap bisa (status, pembatal, alasan; alokasi dibatalkan; tagihan belum_bayar).
        $this->postJson("/api/v1/admin/dues-payments/$id/reverse", ['alasan' => 'Salah catat rumah, dicatat ulang.'],
            ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $this->assertSame('dibatalkan', DB::table('pembayaran_iuran')->where('id', $id)->value('status'));
        $this->assertSame('belum_bayar', DB::table('tagihan_iuran')->where('id', $tagihan)->value('status'));
        // Sudah dibatalkan: tidak bisa "dihidupkan" lagi langsung di database.
        $blocked(fn () => DB::table('pembayaran_iuran')->where('id', $id)->update(['status' => 'tercatat', 'dibatalkan_at' => null]), 'hidupkan lagi');
    }
}
