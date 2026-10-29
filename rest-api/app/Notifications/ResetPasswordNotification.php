<?php

namespace App\Notifications;

use App\Models\Akun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Email reset kata sandi RT05 TAKEDA.
 * Diantrekan setelah commit dan payload dienkripsi agar token tidak tersimpan polos.
 */
class ResetPasswordNotification extends Notification implements ShouldBeEncrypted, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $token)
    {
        $this->onQueue('default');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($reason = self::blockedReason()) {
            Log::warning('Email reset tidak dikirim: '.$reason);

            return false;
        }

        return true;
    }

    public static function blockedReason(): ?string
    {
        if (self::mailerUses((string) config('mail.default'), ['log'])) {
            return 'mailer bertransport log atau tidak dikenal; token tidak boleh masuk log. Konfigurasikan SMTP.';
        }
        if (! app()->environment('local', 'testing') && ! str_starts_with((string) config('app.url'), 'https://')) {
            return 'APP_URL harus HTTPS.';
        }

        return null;
    }

    public static function mailerUses(string $mailer, array $transports, int $depth = 0): bool
    {
        $config = config("mail.mailers.$mailer");
        if (! is_array($config) || $depth > 5) {
            return true;
        }
        $transport = $config['transport'] ?? null;
        if (in_array($transport, $transports, true)) {
            return true;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            foreach ((array) ($config['mailers'] ?? []) as $inner) {
                if (self::mailerUses((string) $inner, $transports, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function toMail(Akun $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.akun.expire', 60);

        return (new MailMessage)
            ->subject('Reset Kata Sandi - RT05 TAKEDA')
            ->greeting('Halo '.$notifiable->nama.',')
            ->line('Kami menerima permintaan untuk mereset kata sandi akun RT05 TAKEDA Anda.')
            ->action('Reset Kata Sandi', self::url($this->token, $notifiable->email))
            ->line('Jika Anda menggunakan aplikasi Android, salin kode berikut dan tempel di halaman Lupa Kata Sandi:')
            ->line('**'.$this->token.'**')
            ->line("Kode dan tautan hanya berlaku selama $minutes menit dan hanya dapat digunakan satu kali.")
            ->line('Jika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tetap aman.')
            ->salutation('Salam, Pengurus RT 05 Taman Kedaung');
    }

    public static function url(string $token, string $email): string
    {
        return rtrim((string) config('app.url'), '/').'/reset-password#'.http_build_query([
            'token' => $token,
            'email' => $email,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
