<?php

namespace App\Console\Commands;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * M16.5: kirim satu email uji secara langsung (bukan antrean) lewat mailer default, untuk membuktikan SMTP/DNS di
 * hosting tanpa SSH (cron sekali jalan). Isi email tanpa data warga; output menyamarkan alamat penerima dan tidak
 * pernah mencetak kredensial. Ditolak bila mailer masih transport log (aturan yang sama dengan email reset).
 */
class MailTest extends Command
{
    protected $signature = 'rt05:mail-test {email : Alamat penerima uji (milik pemilik/pengurus)}';

    protected $description = 'Kirim satu email uji lewat mailer production';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (Validator::make(['email' => $email], ['email' => 'required|email:rfc'])->fails()) {
            $this->error('Alamat email tidak sah.');

            return self::INVALID;
        }
        if ($reason = ResetPasswordNotification::blockedReason()) {
            $this->error('Ditolak: '.$reason);

            return self::FAILURE;
        }
        $mailer = (string) config('mail.default');
        $masked = self::mask($email);
        try {
            Mail::mailer($mailer)->raw(
                'Ini email uji RT05 TAKEDA dari '.config('app.url').' pada '.now('Asia/Jakarta')->format('d M Y H.i')." WIB.\n"
                ."Jika email ini sampai di kotak masuk (bukan spam), pengiriman email reset kata sandi siap dipakai.\n"
                .'Tidak perlu dibalas.',
                fn ($m) => $m->to($email)->subject('Uji email RT05 TAKEDA'),
            );
        } catch (Throwable $e) {
            $this->error("mail=gagal mailer=$mailer ke=$masked (".class_basename($e).': '.mb_substr($e->getMessage(), 0, 200).')');

            return self::FAILURE;
        }
        $this->line("mail=terkirim mailer=$mailer dari=".config('mail.from.address')." ke=$masked");

        return self::SUCCESS;
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
