<?php

namespace App\Http\Controllers;

use App\Http\Responses\Api;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kontak pengurus untuk tombol WhatsApp di aplikasi (ADR35). Dibaca semua akun aktif, diubah pengurus. */
class SettingsController extends Controller
{
    public function contacts(): JsonResponse
    {
        return Api::data(Settings::contacts());
    }

    public function updateContacts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bendahara_whatsapp' => ['present', 'nullable', 'string', 'max:25', 'regex:/^[0-9+ -]{8,25}$/'],
        ]);

        return Api::data(Settings::putContacts($request->user(), $data));
    }
}
