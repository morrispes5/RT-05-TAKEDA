<?php

namespace Tests\Feature;

use App\Support\Heartbeat;
use App\Support\SharedHosting;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;
use Throwable;

/**
 * Profil shared: email reset dikerjakan setelah response, heartbeat mengikuti cron,
 * dan tanpa pcntl tidak ada penghentian paksa satu job.
 */
class SharedRuntimeTest extends TestCase
{
    use Fixtures;

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

    private function statementTimeout(): string
    {
        return (string) DB::selectOne('SHOW statement_timeout')->statement_timeout;
    }
}
