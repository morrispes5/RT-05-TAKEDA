<?php

namespace App\Console\Commands;

use App\Models\Akun;
use App\Models\PreferensiNotifikasi;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bootstrap/grant/revoke pengurus oleh operator (BR v1: bukan lewat API/registrasi).
 * Tanpa password default di Git: password dibuat acak dan ditampilkan sekali, atau dibaca dari
 * env RT05_ADMIN_PASSWORD saat dijalankan. Setiap aksi diaudit.
 */
class PengurusAccount extends Command
{
    protected $signature = 'rt05:pengurus {aksi : buat|grant|revoke} {email} {--nama=Pengurus RT}';

    protected $description = 'Kelola akun pengurus (operator)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::INVALID;
        }

        return match ($this->argument('aksi')) {
            'buat' => $this->create($email),
            'grant' => $this->setRole($email, 'pengurus'),
            'revoke' => $this->setRole($email, 'warga'),
            default => self::INVALID,
        };
    }

    private function create(string $email): int
    {
        if (Akun::where('email', $email)->exists()) {
            $this->error('Akun sudah ada; gunakan grant.');

            return self::FAILURE;
        }
        $password = (string) (getenv('RT05_ADMIN_PASSWORD') ?: Str::password(16, symbols: false));
        DB::transaction(function () use ($email, $password) {
            $akun = new Akun(['nama' => (string) $this->option('nama'), 'email' => $email, 'password_hash' => $password, 'jenis' => 'utama', 'status' => 'aktif']);
            $akun->peran = 'pengurus';
            $akun->save();
            PreferensiNotifikasi::create(['akun_id' => $akun->id]);
            Audit::record(null, 'akun.pengurus_dibuat_operator', 'akun', $akun->id);
        });
        $this->info("Akun pengurus dibuat: $email");
        if (! getenv('RT05_ADMIN_PASSWORD')) {
            $this->warn('Password sementara (tampil sekali, segera ganti di aplikasi): '.$password);
        }

        return self::SUCCESS;
    }

    private function setRole(string $email, string $role): int
    {
        $akun = Akun::where('email', $email)->first();
        if (! $akun) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($akun, $role) {
            $akun->peran = $role;
            $akun->save();
            $akun->tokens()->delete();
            Audit::record(null, 'akun.peran_'.$role.'_operator', 'akun', $akun->id);
        });
        $this->info("Peran $email: $role (sesi lama dicabut).");

        return self::SUCCESS;
    }
}
