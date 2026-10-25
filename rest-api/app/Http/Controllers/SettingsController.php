<?php

namespace App\Http\Controllers;

use App\Http\Responses\Api;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Kontak pengurus untuk tombol WhatsApp di aplikasi (ADR35). Dibaca semua akun aktif, diubah pengurus. */
class SettingsController extends Controller
{
    public function contacts(): JsonResponse
    {
        return Api::data(Settings::contacts());
    }

    /** ADR43: publik untuk halaman Kontak website; hanya nomor Ketua RT (bendahara tetap di aplikasi). */
    public function publicContacts(): JsonResponse
    {
        return Api::data(['ketua_whatsapp' => Settings::contacts()['ketua_whatsapp']])->header('Cache-Control', 'public, max-age=60');
    }

    public function updateContacts(Request $request): JsonResponse
    {
        $phone = ['nullable', 'string', 'max:25', 'regex:/^[0-9+ -]{8,25}$/'];
        $data = $request->validate([
            'bendahara_whatsapp' => ['sometimes', ...$phone],
            'ketua_whatsapp' => ['sometimes', ...$phone],
        ]);
        if ($data === []) {
            throw ValidationException::withMessages(['bendahara_whatsapp' => 'Isi minimal satu nomor kontak (boleh dikosongkan dengan null).']);
        }

        return Api::data(Settings::putContacts($request->user(), $data));
    }
}
