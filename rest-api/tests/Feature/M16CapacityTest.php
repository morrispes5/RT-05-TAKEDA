<?php

namespace Tests\Feature;

use App\Domain\Community\Emergencies;
use App\Domain\Community\Push\PushGateway;
use App\Domain\Community\Push\PushResult;
use App\Domain\Community\PushDelivery;
use App\Domain\Finance\Dues;
use App\Domain\Identity\Registration;
use App\Jobs\ProcessOutboxEvent;
use App\Models\Akun;
use App\Models\Notifikasi;
use App\Models\OutboxEvent;
use App\Models\PerangkatPush;
use App\Models\Rumah;
use App\Support\Privacy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M16 (10 Nov 2026): simulasi kapasitas RT dengan banyak rumah. Hosting production = 1 CPU, RAM 1 GB, 20 proses PHP
 * bersamaan; tiap query ke Neon lewat jembatan ±20–60 ms. Yang harus dibuktikan: biaya layar warga TIDAK naik
 * dengan jumlah rumah (jumlah query tetap), memori per request jauh di bawah 1 GB / 20, dan alarm/pengumuman ke
 * semua HP selesai dalam beberapa putaran job pendek. Bawaan 60 rumah agar CI cepat; bukti 400:
 *   RT05_CAPACITY_N=400 php artisan test --filter=M16CapacityTest
 * Semua data sintetis (nama "Uji", blok fiktif) dan di-rollback.
 */
#[Group('capacity')]
class M16CapacityTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    /** Anggaran query layar warga = anggaran M15 (diukur dengan 1 rumah). Tidak boleh naik dengan jumlah rumah. */
    private const WARGA_BUDGET = ['/api/v1/auth/me' => 6, '/api/v1/agendas' => 3, '/api/v1/announcements' => 3,
        '/api/v1/notifications' => 4, '/api/v1/dues/my' => 17, '/api/v1/household' => 10];

    private array $report = [];

    public function test_many_households_keep_warga_screens_flat_and_fan_out_finishes(): void
    {
        $n = max(10, (int) (getenv('RT05_CAPACITY_N') ?: 60));
        // Sama dengan production: keuangan v2, panik untuk semua akun.
        config(['panic.enabled' => true, 'panic.audience' => 'all', 'finance.v2' => true]);
        DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":true}']);
        Queue::fake();

        $t = microtime(true);
        $code = $this->freshToken();
        $akun = [];
        for ($i = 1; $i <= $n; $i++) {
            $akun[$i] = Registration::primary([
                'email' => "kapasitas$i@contoh.test", 'password' => 'Rahasia123', 'nama_kepala' => "Uji Kapasitas $i",
                'whatsapp' => null, 'blok' => 'K'.intdiv($i - 1, 50), 'jalan' => 'Jl. Uji', 'nomor' => (string) $i,
                'jenis_hunian' => 'pemilik', 'anggota' => [['nama' => "Uji Anak $i", 'hubungan_keluarga' => 'Anak']],
            ], $code);
        }
        $this->metric('seed', "$n registrasi warga utama", microtime(true) - $t);

        DB::table('akun')->whereIn('id', array_map(fn ($a) => $a->id, $akun))
            ->update(['privasi_versi' => Privacy::version(), 'privasi_disetujui_at' => now()]);
        Rumah::query()->update(['tagihan_aktif' => true, 'mulai_tagih' => now('Asia/Jakarta')->subMonths(2)->startOfMonth()->toDateString()]);

        // Tagihan bulanan untuk semua rumah (scheduler tanggal 1 / catch-up): satu transaksi, harus selesai dalam detik.
        [$secs, $queries] = $this->measure(fn () => Dues::catchUp());
        $tagihan = DB::table('tagihan_iuran')->count();
        $this->metric('tagihan', "catch-up tagihan $n rumah → $tagihan tagihan, $queries query", $secs);
        $this->assertGreaterThanOrEqual($n, $tagihan);
        $this->assertSame(0, $tagihan % $n, 'setiap rumah harus mendapat jumlah tagihan yang sama');

        $tokens = [];
        foreach ($akun as $i => $a) {
            $access = $a->createToken('hp');
            $tokens[$i] = $access->plainTextToken;
            $fcm = 'uji-fcm-'.$i.'-'.str_repeat('a', 24);
            (new PerangkatPush)->forceFill(['device_identifier' => 'uji-hp-'.$i, 'akun_id' => $a->id,
                'personal_access_token_id' => $access->accessToken->getKey(), 'token_hash' => hash('sha256', $fcm),
                'fcm_token' => $fcm, 'platform' => 'android', 'last_seen_at' => now()])->save();
        }
        $admin = $this->pengurus('kapasitas-pengurus@contoh.test');
        $admin->forceFill(['privasi_versi' => Privacy::version(), 'privasi_disetujui_at' => now()])->save();
        $adminToken = $admin->createToken('hp')->plainTextToken;

        // Layar yang dimuat aplikasi warga saat dibuka (warga terakhir: data paling banyak di depannya).
        $warga = $tokens[$n];
        foreach (array_keys(self::WARGA_BUDGET) + [6 => '/api/v1/emergencies/status', 7 => '/api/v1/cash/summary',
            8 => '/api/v1/complaint-categories', 9 => '/api/v1/settings/contacts'] as $uri) {
            $this->screen('warga', $warga, $uri, self::WARGA_BUDGET[$uri] ?? null);
        }
        foreach (['/api/v1/admin/dashboard', '/api/v1/admin/houses', '/api/v1/admin/dues-recap', '/api/v1/admin/cash/summary',
            '/api/v1/admin/accounts', '/api/v1/admin/age-stats', '/api/v1/admin/finance-reports', '/api/v1/dues/transparency'] as $uri) {
            $this->screen('admin', $adminToken, $uri, null);
        }

        // Alarm panik ke semua HP: inbox untuk setiap akun dalam satu job, lalu push bertahap (50 penerima per
        // batch, 20 kiriman FCM paralel). Gateway palsu meniru FCM 150 ms per gelombang 20 kiriman.
        $gateway = new class implements PushGateway
        {
            public int $sent = 0;

            public function configured(): bool
            {
                return true;
            }

            public function send(array $messages): array
            {
                usleep(150000 * (int) ceil(count($messages) / 20));
                $this->sent += count($messages);

                return array_map(fn () => PushResult::Sent, $messages);
            }
        };
        $this->app['auth']->forgetGuards();
        $id = $this->withToken($tokens[1])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/emergencies', [
            'jenis' => 'kebakaran', 'konfirmasi' => true, 'versi_ketentuan' => Emergencies::TERMS])->assertCreated()->json('data.id');
        $out = OutboxEvent::where('aggregate_id', $id)->where('type', 'darurat.dibuat')->firstOrFail();
        [$secs, $queries] = $this->measure(fn () => (new ProcessOutboxEvent($out->id))->handle());
        $inbox = Notifikasi::where('event_id', $out->id)->count();
        $this->metric('panik', "inbox $inbox penerima dalam 1 job, $queries query", $secs);
        $this->assertGreaterThanOrEqual($n, $inbox);
        // Pelapor tidak menerima alarmnya sendiri; akun pengurus sintetis tidak punya HP → n − 1 HP.
        $this->fanOut('panik', $out->id, $gateway, $n - 1);

        // Pengumuman pengurus ke semua warga.
        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/admin/announcements', [
            'judul' => 'Uji kapasitas', 'isi' => 'Pengumuman sintetis untuk uji kapasitas.'])->assertSuccessful();
        $ann = OutboxEvent::where('type', 'pengumuman.terbit')->firstOrFail();
        [$secs, $queries] = $this->measure(fn () => (new ProcessOutboxEvent($ann->id))->handle());
        $this->metric('pengumuman', 'inbox '.Notifikasi::where('event_id', $ann->id)->count()." penerima, $queries query", $secs);
        $gateway->sent = 0;
        $this->fanOut('pengumuman', $ann->id, $gateway, $n);

        fwrite(STDERR, "\n== M16 kapasitas: $n rumah ==\n".implode("\n", $this->report)."\n");
    }

    private function screen(string $role, string $token, string $uri, ?int $budget): void
    {
        $this->app['auth']->forgetGuards();
        gc_collect_cycles();
        memory_reset_peak_usage();
        $base = memory_get_usage();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $t = microtime(true);
        $r = $token === '' ? $this->getJson($uri) : $this->withToken($token)->getJson($uri);
        $ms = (microtime(true) - $t) * 1000;
        $log = DB::getQueryLog();
        $q = count($log);
        DB::disableQueryLog();
        usort($log, fn ($a, $b) => $b['time'] <=> $a['time']);
        $slow = $log[0] ?? ['time' => 0, 'query' => ''];
        $mb = (memory_get_peak_usage() - $base) / 1048576;
        $this->report[] = sprintf('%-6s %-34s %3d %3d query %6.0f ms +%5.1f MB  %7d B  terlama %4.0f ms %s', $role, $uri, $r->status(), $q, $ms, $mb,
            strlen($r->getContent()), $slow['time'], substr(preg_replace('/\s+/', ' ', $slow['query']), 0, 70));
        $this->assertSame(200, $r->status(), "$uri harus 200");
        if ($budget !== null) {
            $this->assertLessThanOrEqual($budget, $q, "$uri memakai $q query; tidak boleh naik dengan jumlah rumah");
        }
        $this->assertLessThan(48, $mb, "$uri memakai {$mb} MB; 20 proses × 48 MB < 1 GB");
    }

    private function fanOut(string $label, string $eventId, PushGateway $gateway, int $n): void
    {
        $t = microtime(true);
        $rounds = 0;
        do {
            $rounds++;
            // Satu job SendPush = maksimal 15 dtk (rt05.push.run_seconds); sisa dilanjutkan job berikutnya.
            $result = PushDelivery::deliver($eventId, $gateway);
        } while ($result === 'more' && $rounds < 50);
        $this->metric($label, "push {$gateway->sent} HP dalam $rounds job SendPush", microtime(true) - $t);
        $this->assertSame('done', $result);
        $this->assertGreaterThanOrEqual($n, $gateway->sent);
        $this->assertSame(0, Notifikasi::where('event_id', $eventId)->whereNull('push_status')->count());
    }

    /** @return array{0: float, 1: int} */
    private function measure(callable $fn): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $t = microtime(true);
        $fn();
        $secs = microtime(true) - $t;
        $q = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return [$secs, $q];
    }

    private function metric(string $label, string $what, float $secs): void
    {
        $this->report[] = sprintf('%-10s %-62s %6.2f dtk', $label, $what, $secs);
    }
}
