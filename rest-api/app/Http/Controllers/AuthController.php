<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Registration;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\Akun;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function passwordRule(): PasswordRule
    {
        return PasswordRule::min(8)->letters()->numbers();
    }

    /** POST /auth/register — akun utama + rumah + keluarga dalam satu transaksi (FR03). */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
            'registration_token' => ['required', 'string', 'size:6'],
            'device_name' => ['required', 'string', 'max:100'],
            'nama_kepala' => ['required', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+ -]{8,25}$/'],
            'blok' => ['nullable', 'string', 'max:30'],
            'jalan' => ['required', 'string', 'max:150'],
            'nomor' => ['required', 'string', 'max:20'],
            'jenis_hunian' => ['required', 'in:pemilik,kontrak'],
            'anggota' => ['array', 'max:15'],
            'anggota.*.nama' => ['required', 'string', 'max:120'],
            'anggota.*.hubungan_keluarga' => ['required', 'string', 'max:40'],
        ]);

        $akun = Registration::primary($data, $data['registration_token']);

        return Api::data($this->issueToken($akun, $data['device_name']), 201);
    }

    /** POST /auth/additional-register — akun tambahan menunggu persetujuan pengurus (FR05). */
    public function additionalRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
            'registration_token' => ['required', 'string', 'size:6'],
            'device_name' => ['required', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:120'],
            'hubungan_keluarga' => ['required', 'string', 'max:40'],
            'blok' => ['nullable', 'string', 'max:30'],
            'jalan' => ['required', 'string', 'max:150'],
            'nomor' => ['required', 'string', 'max:20'],
        ]);

        $akun = Registration::additional($data, $data['registration_token']);

        return Api::data($this->issueToken($akun, $data['device_name']), 202);
    }

    /** POST /auth/mobile-login — bearer Sanctum per perangkat, berlaku 30 hari. */
    public function mobileLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $akun = Akun::where('email', strtolower(trim($data['email'])))->first();
        if (! $akun || ! Hash::check($data['password'], $akun->password_hash) || in_array($akun->status, ['nonaktif', 'diarsipkan'], true)) {
            throw ValidationException::withMessages(['email' => ['Email atau kata sandi salah.']]);
        }

        Audit::record($akun, 'akun.login', 'akun', $akun->id, ['perangkat' => mb_substr($data['device_name'], 0, 60)]);

        return Api::data($this->issueToken($akun, $data['device_name']));
    }

    public function me(Request $request): JsonResponse
    {
        return Api::data(Present::akun($request->user()->load('warga.keluarga.penghunianAktif.rumah')));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return Api::noContent();
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:sanctum']]);
        $request->user()->tokens()->delete();
        Audit::record($request->user(), 'akun.logout_semua', 'akun', $request->user()->id);

        return Api::noContent();
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', $this->passwordRule()],
        ]);
        $akun = $request->user();
        $akun->forceFill(['password_hash' => $data['password']])->save();
        $current = $akun->currentAccessToken()?->id;
        $akun->tokens()->where('id', '!=', $current)->delete();
        Audit::record($akun, 'akun.ganti_password', 'akun', $akun->id);

        return Api::data(['message' => 'Kata sandi diperbarui. Perangkat lain telah dikeluarkan.']);
    }

    /** Respons selalu generik agar tidak membuka daftar email terdaftar. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:254']]);
        Password::broker('akun')->sendResetLink(['email' => strtolower(trim($request->string('email')))]);

        return Api::data(['message' => 'Jika email terdaftar, tautan reset kata sandi telah dikirim.'], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);
        $status = Password::broker('akun')->reset(
            ['email' => strtolower(trim($data['email'])), 'token' => $data['token'], 'password' => $data['password'], 'password_confirmation' => $data['password']],
            function (Akun $akun, string $password) {
                $akun->forceFill(['password_hash' => $password])->save();
                $akun->tokens()->delete();
                Audit::record($akun, 'akun.reset_password', 'akun', $akun->id);
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['Tautan reset tidak valid atau kedaluwarsa.']]);
        }

        return Api::data(['message' => 'Kata sandi berhasil direset. Silakan masuk kembali.']);
    }

    public function additionalAccountStatus(Request $request): JsonResponse
    {
        $p = $request->user()->permohonan;

        return Api::data([
            'status_akun' => $request->user()->status,
            'permohonan' => $p ? ['jenis' => $p->jenis, 'status' => $p->status, 'catatan' => $p->status === 'ditolak' ? $p->catatan : null, 'created_at' => Present::ts($p->created_at)] : null,
        ]);
    }

    private function issueToken(Akun $akun, string $device): array
    {
        $expires = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $akun->createToken(mb_substr($device, 0, 100), ['*'], $expires);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => Present::ts($expires),
            'akun' => Present::akun($akun->fresh()->load('warga.keluarga.penghunianAktif.rumah')),
        ];
    }
}
