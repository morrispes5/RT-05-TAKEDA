<?php

namespace App\Providers;

use App\Domain\Community\Push\FcmGateway;
use App\Domain\Community\Push\NullGateway;
use App\Domain\Community\Push\PushGateway;
use App\Support\Heartbeat;
use App\Support\SharedHosting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushGateway::class, fn () => config('rt05.push.driver') === 'fcm'
            ? new FcmGateway((array) config('rt05.push.fcm'))
            : new NullGateway);
    }

    public function boot(): void
    {
        // Budget IP membatasi kerja hash meski email berganti; bucket pasangan tetap melindungi tiap akun.
        $email = fn (Request $r) => strtolower(trim((string) $r->input('email')));
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(max(1, (int) config('rt05.auth.login_ip_per_minute', 60)))->by('ip:'.$r->ip()),
            Limit::perMinute(5)->by('email:'.$r->ip().'|'.$email($r)),
        ]);
        RateLimiter::for('registrasi', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('lupa-password', fn (Request $r) => [
            Limit::perMinutes(15, max(1, (int) config('rt05.auth.forgot_ip_per_15_minutes', 20)))->by('ip:'.$r->ip()),
            Limit::perMinutes(15, 3)->by('email:'.$r->ip().'|'.$email($r)),
        ]);
        // Submit reset memakai bucket sendiri: permintaan lupa password tidak menghabiskan kuota
        // reset dengan token yang masih berlaku. Token reset 64 hex, jadi batas ini untuk biaya bcrypt.
        RateLimiter::for('reset-password', fn (Request $r) => [
            Limit::perMinutes(15, max(1, (int) config('rt05.auth.reset_ip_per_15_minutes', 60)))->by('ip:'.$r->ip()),
            Limit::perMinutes(15, 10)->by('email:'.$r->ip().'|'.$email($r)),
        ]);
        RateLimiter::for('laporan', fn (Request $r) => Limit::perHour(20)->by((string) $r->user()?->id));

        if (SharedHosting::enabled() && (config('mail.mailers.smtp.timeout') === null || config('mail.mailers.smtp.timeout') === '')) {
            config(['mail.mailers.smtp.timeout' => SharedHosting::COOPERATIVE_DEADLINE_SECONDS]);
        }

        // Job database (termasuk email reset, yang tidak lewat outbox) ikut jalur setelah
        // response. Pendaftaran drain satu kali per proses; reservasi antrean menolak job ganda.
        Event::listen(function (JobQueued $event): void {
            if ($event->connectionName === 'database') {
                SharedHosting::wake();
                SharedHosting::drainAfterResponse();
            }
        });

        Event::listen(function (JobProcessing $event): void {
            if ($event->connectionName === 'database') {
                SharedHosting::armOperationDeadlines();
            }
        });

        // Heartbeat worker tiap putaran loop queue:work (dibatasi write_interval). Listener tidak
        // mengembalikan nilai: return false pada event Looping akan menghentikan worker.
        Event::listen(function (Looping $event): void {
            Heartbeat::beat(Heartbeat::workerComponent($event->queue), throttle: true);
        });
    }
}
