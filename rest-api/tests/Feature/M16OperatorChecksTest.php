<?php

namespace Tests\Feature;

use App\Console\Commands\MailTest;
use App\Domain\Community\Push\FcmGateway;
use App\Domain\Community\Push\PushGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * M16.5: perintah cek operator untuk hosting tanpa SSH (cron sekali jalan). rt05:mail-test mengirim satu email langsung
 * lewat mailer default dan menolak transport log; rt05:push-check membuktikan OAuth + HTTPS FCM tanpa perangkat nyata.
 */
class M16OperatorChecksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_mail_test_sends_one_direct_email_and_masks_the_recipient(): void
    {
        config(['mail.default' => 'array']);
        $this->artisan('rt05:mail-test', ['email' => 'pemilik@contoh.test'])
            ->expectsOutputToContain('mail=terkirim mailer=array')
            ->expectsOutputToContain('ke=pe***@contoh.test')
            ->doesntExpectOutputToContain('pemilik@contoh.test')
            ->assertSuccessful();

        $messages = $this->app['mail.manager']->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Uji email RT05 TAKEDA', $messages[0]->getOriginalMessage()->getSubject());
        $this->assertSame('pe***@contoh.test', MailTest::mask('pemilik@contoh.test'));
    }

    public function test_mail_test_refuses_the_log_transport_and_bad_addresses(): void
    {
        config(['mail.default' => 'log']);
        $this->artisan('rt05:mail-test', ['email' => 'pemilik@contoh.test'])->expectsOutputToContain('Ditolak')->assertFailed();
        config(['mail.default' => 'array']);
        $this->artisan('rt05:mail-test', ['email' => 'bukan-email'])->assertExitCode(2);
        $this->assertCount(0, $this->app['mail.manager']->mailer('array')->getSymfonyTransport()->messages());
    }

    private function configureFcm(): void
    {
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + (is_file($cnf) && ! getenv('OPENSSL_CONF') ? ['config' => $cnf] : []);
        $key = openssl_pkey_new($opts);
        $this->assertNotFalse($key, 'openssl_pkey_new');
        openssl_pkey_export($key, $pem, null, $opts);
        config(['rt05.push.driver' => 'fcm', 'rt05.push.fcm' => [
            'project_id' => null, 'credentials_path' => null,
            'credentials_base64' => base64_encode(json_encode([
                'type' => 'service_account', 'project_id' => 'rt05-uji',
                'client_email' => 'push@rt05-uji.iam.gserviceaccount.com', 'private_key' => $pem,
            ])),
            'timeout' => 5, 'concurrency' => 10,
        ]]);
        $this->app->forgetInstance(PushGateway::class);
    }

    private function fakeFcm(int $status, array $body): void
    {
        Http::fake(function (HttpRequest $request) use ($status, $body) {
            if (str_starts_with($request->url(), 'https://oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'akses-uji', 'expires_in' => 3600]);
            }

            return Http::response($body, $status);
        });
    }

    public function test_push_check_proves_oauth_and_fcm_without_a_real_device(): void
    {
        $this->configureFcm();
        $this->fakeFcm(400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token']]);

        $this->artisan('rt05:push-check')
            ->expectsOutputToContain('fcm=ok project=rt05-uji oauth=ok status=400 kode=INVALID_ARGUMENT')
            ->doesntExpectOutputToContain('akses-uji')
            ->assertSuccessful();

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://fcm.googleapis.com/v1/projects/rt05-uji/messages:send'
            && $r->data()['validate_only'] === true && $r->data()['message']['token'] === FcmGateway::PROBE_TOKEN
            && ! isset($r->data()['message']['notification']));
    }

    public function test_push_check_fails_on_permission_errors_and_without_credentials(): void
    {
        $this->configureFcm();
        $this->fakeFcm(403, ['error' => ['status' => 'PERMISSION_DENIED']]);
        $this->artisan('rt05:push-check')->expectsOutputToContain('fcm=ditolak project=rt05-uji oauth=ok status=403 kode=PERMISSION_DENIED')->assertFailed();

        config(['rt05.push.fcm' => ['credentials_base64' => null, 'credentials_path' => null]]);
        $this->app->forgetInstance(PushGateway::class);
        $this->artisan('rt05:push-check')->expectsOutputToContain('fcm=belum_dikonfigurasi')->assertFailed();

        config(['rt05.push.driver' => 'null']);
        $this->app->forgetInstance(PushGateway::class);
        $this->artisan('rt05:push-check')->expectsOutputToContain('push=nonaktif')->assertFailed();
    }
}
