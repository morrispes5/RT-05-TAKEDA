<?php

namespace Tests\Feature;

use App\Domain\Community\Emergencies;
use App\Domain\Community\Push\PushGateway;
use App\Support\Heartbeat;
use App\Support\Privacy;
use App\Support\SharedHosting;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\CommittedData;
use Tests\Support\Fixtures;
use Tests\TestCase;
use Throwable;

/**
 * Profil shared: email reset dikerjakan setelah response, heartbeat mengikuti cron,
 * dan tanpa pcntl tidak ada penghentian paksa satu job.
 */
class SharedRuntimeTest extends TestCase
{
    use CommittedData, Fixtures;

    private ?string $resetEmail = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'rt05.deployment_profile' => 'shared',
            'queue.default' => 'database',
            'rt05.outbox.queue_connection' => 'database',
            'cache.default' => 'file',
            'session.driver' => 'file',
            'rt05.heartbeat.store' => 'file',
            'rt05.heartbeat.components' => ['shared-tick'],
            'rt05.heartbeat.max_age_seconds' => null,
            'app.url' => 'https://takeda-staging.morriz.tech',
        ]);
        Cache::store('file')->forget('rt05:shared:awake');
    }

    protected function tearDown(): void
    {
        if ($this->resetEmail !== null) {
            DB::table('password_reset_tokens')->where('email', $this->resetEmail)->delete();
            DB::table('akun')->where('email', $this->resetEmail)->delete();
        }
        DB::table('jobs')->delete();
        try {
            DB::statement('RESET statement_timeout');
        } catch (Throwable) {
        }
        ini_set('default_socket_timeout', '60');
        set_time_limit(0);
        Cache::store('file')->forget('rt05:shared:awake');
        parent::tearDown();
    }

    public function test_password_reset_mail_is_sent_after_the_response_without_cron_and_only_once(): void
    {
        $sent = fn () => count($this->app['mailer']->getSymfonyTransport()->messages());
        $this->resetEmail = 'shared-reset-'.bin2hex(random_bytes(4)).'@contoh.test';
        $this->pengurus($this->resetEmail);

        $kernel = $this->app->make(Kernel::class);
        $request = Request::create('/api/v1/auth/forgot-password', 'POST', ['email' => $this->resetEmail]);
        $request->headers->set('Accept', 'application/json');
        $response = $kernel->handle($request);
        $this->assertSame(202, $response->getStatusCode());

        $this->assertSame(0, $sent(), 'Email hanya diantrekan selama request.');
        $this->assertSame(1, DB::table('jobs')->where('queue', 'default')->count());
        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertStringNotContainsString($this->resetEmail, $payload);
        $this->assertTrue(SharedHosting::awake());

        $kernel->terminate($request, $response);

        $this->assertSame(1, $sent());
        $this->assertSame(0, DB::table('jobs')->count());
        $html = $this->app['mailer']->getSymfonyTransport()->messages()[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('https://takeda-staging.morriz.tech/reset-password#token=', $html);

        $kernel->terminate($request, $response);

        $this->assertSame(1, $sent(), 'Callback setelah response yang terpanggil lagi tidak mengirim ulang.');
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_shared_heartbeat_allows_one_cron_interval_plus_late_grace_only(): void
    {
        config([
            'rt05.heartbeat.store' => 'array',
            'rt05.heartbeat.max_age_seconds' => null,
            'rt05.heartbeat.components' => ['shared-tick'],
        ]);
        Cache::store('array')->flush();

        $this->assertSame(
            SharedHosting::SWEEP_SECONDS + SharedHosting::HEARTBEAT_LATE_GRACE_SECONDS,
            Heartbeat::maxAge()
        );
        $this->assertSame(1200, Heartbeat::maxAge());
        $this->assertLessThan(SharedHosting::SWEEP_SECONDS * 2, Heartbeat::maxAge());

        Cache::store('array')->put('rt05:heartbeat:shared-tick', time() - SharedHosting::SWEEP_SECONDS);
        $this->artisan('rt05:heartbeat-check', ['--component' => ['shared-tick']])
            ->expectsOutputToContain('shared-tick ok')->assertExitCode(0);

        Cache::store('array')->put('rt05:heartbeat:shared-tick', time() - Heartbeat::maxAge());
        $this->assertLessThanOrEqual(Heartbeat::maxAge(), Heartbeat::age('shared-tick'));

        Cache::store('array')->put('rt05:heartbeat:shared-tick', time() - (Heartbeat::maxAge() + 1));
        $this->artisan('rt05:heartbeat-check', ['--component' => ['shared-tick']])
            ->expectsOutputToContain('stale')->assertExitCode(1);

        config(['rt05.heartbeat.max_age_seconds' => 90]);
        $this->assertSame(90, Heartbeat::maxAge());

        config([
            'rt05.deployment_profile' => 'vps',
            'rt05.heartbeat.max_age_seconds' => null,
        ]);
        $this->assertSame(300, Heartbeat::maxAge());
    }

    public function test_without_pcntl_bounds_blocking_work_and_does_not_claim_a_hard_kill(): void
    {
        $this->assertFalse(SharedHosting::timeoutPolicy(false)['hard_kill']);
        $this->assertSame(0, SharedHosting::timeoutPolicy(false)['worker_timeout']);
        $this->assertSame(20, SharedHosting::timeoutPolicy(false)['cooperative_deadline']);
        $this->assertSame(0, SharedHosting::workerArguments(40, false)['--timeout']);
        $this->assertSame(40, SharedHosting::workerArguments(40, false)['--max-time']);

        $this->assertTrue(SharedHosting::timeoutPolicy(true)['hard_kill']);
        $this->assertSame(SharedHosting::JOB_TIMEOUT_SECONDS, SharedHosting::timeoutPolicy(true)['worker_timeout']);
        $this->assertNull(SharedHosting::timeoutPolicy(true)['cooperative_deadline']);
        $this->assertSame(40, SharedHosting::workerArguments(40, true)['--timeout']);

        try {
            DB::statement('SET statement_timeout = 0');
            ini_set('default_socket_timeout', '60');
            config(['rt05.deployment_profile' => 'vps', 'mail.mailers.smtp.timeout' => null]);
            SharedHosting::armOperationDeadlines(false);
            $this->assertSame('0', $this->statementTimeout());
            $this->assertSame('60', ini_get('default_socket_timeout'));

            config(['rt05.deployment_profile' => 'shared']);
            SharedHosting::armOperationDeadlines(true);
            $this->assertSame('0', $this->statementTimeout());
            $this->assertSame('60', ini_get('default_socket_timeout'));

            config(['mail.mailers.smtp.timeout' => 15]);
            SharedHosting::armOperationDeadlines(false);
            $this->assertSame(15, config('mail.mailers.smtp.timeout'));
            $this->assertContains($this->statementTimeout(), ['20s', '20000ms', '20000']);
            $this->assertSame('20', ini_get('default_socket_timeout'));
            $this->assertSame('20', ini_get('max_execution_time'));

            config(['mail.mailers.smtp.timeout' => null]);
            SharedHosting::armOperationDeadlines(false);
            $this->assertSame(20, config('mail.mailers.smtp.timeout'));
        } finally {
            DB::statement('RESET statement_timeout');
            ini_set('default_socket_timeout', '60');
            set_time_limit(0);
        }
    }

    /**
     * M10 shared: pengumuman → inbox + push FCM selesai setelah response, tanpa cron dan tanpa
     * worker permanen; target inbox ≤60 detik (SHARED_HOSTING.md).
     */
    public function test_announcement_inbox_and_push_finish_after_the_response_within_60_seconds(): void
    {
        $this->markCommittedData();
        try {
            $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
            $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + (is_file($cnf) && ! getenv('OPENSSL_CONF') ? ['config' => $cnf] : []);
            openssl_pkey_export(openssl_pkey_new($opts), $pem, null, $opts);
            config(['rt05.push.fcm.credentials_base64' => base64_encode(json_encode([
                'type' => 'service_account', 'project_id' => 'rt05-uji', 'client_email' => 'push@rt05-uji.iam.gserviceaccount.com', 'private_key' => $pem,
            ]))]);
            $this->app->forgetInstance(PushGateway::class);
            Http::fake([
                'oauth2.googleapis.com/*' => Http::response(['access_token' => 'akses-uji', 'expires_in' => 3600]),
                'fcm.googleapis.com/*' => Http::response(['name' => 'projects/rt05-uji/messages/1']),
            ]);

            $warga = $this->warga('shared-m10-'.bin2hex(random_bytes(2)));
            $this->withToken($warga->createToken('android')->plainTextToken)
                ->putJson('/api/v1/devices/hp-shared-m10', ['fcm_token' => 'fcm-shared_'.str_repeat('Ab1', 12), 'platform' => 'android'])->assertOk();
            $this->app['auth']->forgetGuards();
            $admin = $this->pengurus('shared-m10-'.bin2hex(random_bytes(3)).'@contoh.test');

            $kernel = $this->app->make(Kernel::class);
            $request = Request::create('/api/v1/admin/announcements', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['judul' => 'Kerja bakti Minggu', 'isi' => 'Kumpul pukul 07.00 di lapangan RT 05.']));
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('Authorization', 'Bearer '.$admin->createToken('android')->plainTextToken);
            $started = microtime(true);
            $response = $kernel->handle($request);
            $this->assertSame(201, $response->getStatusCode());
            $inbox = fn () => DB::table('notifikasi')->where('penerima_id', $warga->id)->where('jenis', 'pengumuman')->first();
            $this->assertNull($inbox(), 'inbox dibuat setelah response, bukan di dalam request');

            $kernel->terminate($request, $response);

            $this->assertLessThan(60, microtime(true) - $started);
            $this->assertNotNull($inbox());
            $this->assertSame('terkirim', $inbox()->push_status);
            Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/rt05-uji/messages:send'));
            $this->assertFalse(SharedHosting::backlog());
        } finally {
            $this->purgeCommittedData();
        }
    }

    /**
     * Audit M19: di shared hosting alarm panik tidak boleh menunggu cron. Semua penerima (di sini 30 akun berperangkat)
     * menerima push alarm setelah respons, dalam satu siklus permintaan, sebelum batas umur alarm 10 menit.
     */
    public function test_panic_alarm_reaches_all_recipients_after_the_response_without_cron(): void
    {
        $this->markCommittedData();
        $setting = DB::table('system_settings')->where('key', 'darurat.fitur')->first();
        try {
            config(['panic.enabled' => true, 'panic.audience' => 'all']);
            DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => '{"aktif":true}']);
            $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
            $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + (is_file($cnf) && ! getenv('OPENSSL_CONF') ? ['config' => $cnf] : []);
            openssl_pkey_export(openssl_pkey_new($opts), $pem, null, $opts);
            config(['rt05.push.fcm.credentials_base64' => base64_encode(json_encode([
                'type' => 'service_account', 'project_id' => 'rt05-uji', 'client_email' => 'push@rt05-uji.iam.gserviceaccount.com', 'private_key' => $pem,
            ]))]);
            $this->app->forgetInstance(PushGateway::class);
            Http::fake([
                'oauth2.googleapis.com/*' => Http::response(['access_token' => 'akses-uji', 'expires_in' => 3600]),
                'fcm.googleapis.com/*' => Http::response(['name' => 'projects/rt05-uji/messages/1']),
            ]);
            $tag = bin2hex(random_bytes(2));
            $recipients = [];
            for ($i = 1; $i <= 30; $i++) {
                $w = $this->warga("sp$tag-$i");
                $w->forceFill(['privasi_versi' => Privacy::PANIC_VERSION, 'privasi_disetujui_at' => now()])->save();
                $this->withToken($w->createToken('android')->plainTextToken)
                    ->putJson("/api/v1/devices/hp-panic-$tag-$i", ['fcm_token' => "fcm-panic-$tag-$i".str_repeat('Ab1', 12), 'platform' => 'android'])->assertOk();
                $this->app['auth']->forgetGuards();
                $recipients[] = $w->id;
            }
            $reporter = $this->warga("sp$tag-p");
            $reporter->forceFill(['privasi_versi' => Privacy::PANIC_VERSION, 'privasi_disetujui_at' => now()])->save();

            $kernel = $this->app->make(Kernel::class);
            $request = Request::create('/api/v1/emergencies', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['jenis' => 'kebakaran', 'konfirmasi' => true, 'versi_ketentuan' => Emergencies::TERMS]));
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('Idempotency-Key', (string) Str::uuid());
            $request->headers->set('Authorization', 'Bearer '.$reporter->createToken('android')->plainTextToken);
            $started = microtime(true);
            $response = $kernel->handle($request);
            $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());
            $kernel->terminate($request, $response);

            $this->assertLessThan(60, microtime(true) - $started);
            $sent = DB::table('notifikasi')->whereIn('penerima_id', $recipients)->where('jenis', 'darurat')->pluck('push_status', 'penerima_id');
            $this->assertCount(30, $sent);
            $this->assertSame(['terkirim'], $sent->unique()->values()->all());
            $this->assertSame(0, DB::table('notifikasi')->where('penerima_id', $reporter->id)->where('jenis', 'darurat')->count());
            Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com') && str_contains((string) $r->body(), '"alarm":"1"'));
            $this->assertFalse(SharedHosting::backlog());
        } finally {
            DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => $setting->value]);
            $this->purgeCommittedData();
        }
    }

    private function statementTimeout(): string
    {
        return (string) DB::selectOne('SHOW statement_timeout')->statement_timeout;
    }
}
