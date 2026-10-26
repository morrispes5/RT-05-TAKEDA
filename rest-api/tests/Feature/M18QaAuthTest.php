<?php

namespace Tests\Feature;

use App\Domain\Identity\PasswordPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\Browser;
use Tests\Support\Fixtures;
use Tests\TestCase;

class M18QaAuthTest extends TestCase
{
    use Browser, DatabaseTransactions, Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBrowser();
    }

    public function test_rotating_recovery_emails_cannot_reset_ip_budget_and_other_ip_still_works(): void
    {
        config(['rt05.auth.forgot_ip_per_15_minutes' => 3]);
        Hash::shouldReceive('make')->times(4)->andReturn('synthetic-dummy');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => "qa-rotate-$i@example.test"])->assertStatus(202);
        }
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ANOTHER@example.test '])->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'qa-other-ip@example.test'])->assertStatus(202);
    }

    public function test_login_mobile_and_browser_share_ip_budget_despite_rotating_email(): void
    {
        config(['rt05.auth.login_ip_per_minute' => 2]);
        Hash::shouldReceive('make')->twice()->andReturn('synthetic-dummy');
        $this->postJson('/api/v1/auth/mobile-login', ['email' => 'qa-one@example.test', 'password' => 'Salah123', 'device_name' => 'qa'])->assertStatus(422);
        $this->cmsLogin('qa-two@example.test', 'Salah123')->assertStatus(422);
        $this->postJson('/api/v1/auth/mobile-login', ['email' => 'qa-three@example.test', 'password' => 'Salah123', 'device_name' => 'qa'])->assertStatus(429);
    }

    public function test_known_and_unknown_login_failures_both_do_hash_work_and_remain_generic(): void
    {
        $akun = $this->warga('qa-auth');
        Hash::shouldReceive('check')->twice()->andReturn(false);
        Hash::shouldReceive('make')->twice()->andReturn('synthetic-dummy');
        $messages = [];
        foreach ([$akun->email, 'qa-missing@example.test'] as $email) {
            $messages[] = $this->postJson('/api/v1/auth/mobile-login', ['email' => $email, 'password' => 'Salah123', 'device_name' => 'qa'])->assertStatus(422)->json('error.message');
            $messages[] = $this->cmsLogin($email, 'Salah123')->assertStatus(422)->json('error.message');
        }
        $this->assertCount(1, array_unique($messages));
    }

    public function test_recent_reset_unknown_and_inactive_recovery_all_do_hash_work_without_extra_email(): void
    {
        Notification::fake();
        $akun = $this->warga('qa-recent');
        $inactive = $this->warga('qa-inactive');
        $inactive->forceFill(['status' => 'nonaktif'])->save();
        Password::broker('akun')->createToken($akun);
        $before = DB::table('password_reset_tokens')->where('email', $akun->email)->value('token');
        Hash::shouldReceive('make')->times(3)->andReturn('synthetic-dummy');
        foreach ([$akun->email, 'qa-unknown@example.test', $inactive->email] as $email) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertStatus(202);
        }
        $this->assertSame($before, DB::table('password_reset_tokens')->where('email', $akun->email)->value('token'));
        Notification::assertNothingSent();
    }

    public function test_missing_and_expired_reset_tokens_do_dummy_work_without_changing_password(): void
    {
        $akun = $this->warga('qa-expired');
        $before = $akun->password_hash;
        $token = Password::broker('akun')->createToken($akun);
        DB::table('password_reset_tokens')->where('email', $akun->email)->update(['created_at' => now()->subMinutes(61)]);
        Hash::shouldReceive('make')->twice()->andReturn('synthetic-dummy');
        foreach ([$akun->email, 'qa-absent@example.test'] as $email) {
            $this->postJson('/api/v1/auth/reset-password', ['email' => $email, 'token' => $token, 'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9'])->assertStatus(422);
        }
        $this->assertSame($before, $akun->fresh()->password_hash);
    }

    public function test_rotating_reset_emails_cannot_reset_aggregate_budget(): void
    {
        config(['rt05.auth.reset_ip_per_15_minutes' => 2]);
        Hash::shouldReceive('make')->twice()->andReturn('synthetic-dummy');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/reset-password', ['email' => "qa-reset-$i@example.test", 'token' => 'invalid', 'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9'])->assertStatus($i < 2 ? 422 : 429);
        }
    }

    public function test_invalid_password_forms_do_safe_work_but_can_never_authenticate(): void
    {
        $akun = $this->warga('qa-bytes');
        Hash::shouldReceive('make')->times(4)->andReturn('synthetic-dummy');
        foreach ([str_repeat('a', 73), "abc\0def"] as $input) {
            $this->assertFalse(PasswordPolicy::matches($akun, $input));
            $this->assertFalse(PasswordPolicy::matches(null, $input));
        }
    }
}
