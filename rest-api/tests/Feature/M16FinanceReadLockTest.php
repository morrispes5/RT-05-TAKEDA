<?php

namespace Tests\Feature;

use App\Domain\Finance\FlexibleDues;
use App\Support\Privacy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M16 (10 Nov 2026): GET Iuran/Kas warga memakai kunci keuangan bersama. Dengan kunci eksklusif, semua pembukaan
 * layar Iuran satu RT antre satu per satu (20 proses PHP hosting bisa habis saat 400 HP dibuka bersamaan).
 * Pembaca kedua harus tetap bisa masuk; penulis harus tetap tertahan selama ada pembaca.
 */
class M16FinanceReadLockTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function other(): PDO
    {
        $c = config('database.connections.pgsql');
        if (! empty($c['url'])) {
            $u = parse_url($c['url']);
            $c = ['host' => $u['host'], 'port' => $u['port'] ?? 5432, 'database' => ltrim($u['path'], '/'),
                'username' => urldecode($u['user']), 'password' => urldecode($u['pass'] ?? '')];
        }

        return new PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function try(PDO $pdo, string $fn): bool
    {
        return (bool) $pdo->query("SELECT $fn(hashtext('rt05:keuangan'))")->fetchColumn();
    }

    public function test_readers_share_the_finance_lock_but_writers_still_wait(): void
    {
        FlexibleDues::readLock();
        $other = $this->other();
        $other->beginTransaction();
        $this->assertTrue($this->try($other, 'pg_try_advisory_xact_lock_shared'), 'Pembaca kedua tidak boleh menunggu pembaca pertama.');
        $other->rollBack();
        $other->beginTransaction();
        $this->assertFalse($this->try($other, 'pg_try_advisory_xact_lock'), 'Penulis harus menunggu selama ada pembaca.');
        $other->rollBack();
    }

    public function test_writer_lock_still_blocks_readers(): void
    {
        FlexibleDues::lock();
        $other = $this->other();
        $other->beginTransaction();
        $this->assertFalse($this->try($other, 'pg_try_advisory_xact_lock_shared'), 'Pembaca harus menunggu pencatatan selesai.');
        $other->rollBack();
    }

    public function test_warga_dues_and_cash_screens_take_only_the_shared_lock(): void
    {
        config(['finance.v2' => true]);
        $akun = $this->warga('kunci1');
        $akun->forceFill(['privasi_versi' => Privacy::version(), 'privasi_disetujui_at' => now()])->save();
        $token = $akun->createToken('hp')->plainTextToken;
        foreach (['/api/v1/dues/my', '/api/v1/cash/summary'] as $uri) {
            $this->app['auth']->forgetGuards();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withToken($token)->getJson($uri)->assertOk();
            $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
            DB::disableQueryLog();
            $this->assertStringContainsString('pg_advisory_xact_lock_shared', $sql, $uri);
            $this->assertDoesNotMatchRegularExpression('/pg_advisory_xact_lock\(/', $sql, "$uri tidak boleh mengambil kunci eksklusif");
        }
    }
}
