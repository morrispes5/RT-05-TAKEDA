<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainConflict;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\PerangkatPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token push perangkat Android (M10, FR20). Hanya sesi mobile (bearer): baris terikat token akses
 * sehingga logout/revoke menghapusnya. Token FCM tidak pernah dikembalikan ke client. Satu token
 * FCM hanya milik satu akun: HP yang berganti akun memindahkan baris, bukan menggandakannya.
 */
class DeviceController extends Controller
{
    public function upsert(Request $request, string $deviceIdentifier): JsonResponse
    {
        $access = $request->user()->currentAccessToken();
        if (! $access instanceof PersonalAccessToken || ! $access->exists) {
            throw new DomainConflict('MOBILE_SESSION_REQUIRED', 'Push hanya untuk sesi aplikasi Android.', 403);
        }
        $data = validator(['device_identifier' => $deviceIdentifier] + $request->only(['fcm_token', 'platform']), [
            'device_identifier' => ['required', 'string', 'regex:/^[A-Za-z0-9._:-]{8,200}$/'],
            'fcm_token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:.-]+$/'],
            'platform' => ['required', 'in:android'],
        ], [
            'device_identifier.regex' => 'ID perangkat tidak valid.',
            'fcm_token.*' => 'Token push tidak valid.',
        ])->validate();
        $hash = hash('sha256', $data['fcm_token']);
        $akun = $request->user();

        $device = DB::transaction(function () use ($deviceIdentifier, $data, $hash, $akun, $access) {
            PerangkatPush::where('token_hash', $hash)->where('device_identifier', '!=', $deviceIdentifier)->delete();
            $device = PerangkatPush::where('device_identifier', $deviceIdentifier)->lockForUpdate()->first() ?? new PerangkatPush(['device_identifier' => $deviceIdentifier]);
            $device->fill([
                'akun_id' => $akun->id,
                'personal_access_token_id' => $access->getKey(),
                'token_hash' => $hash,
                'fcm_token' => $data['fcm_token'],
                'platform' => $data['platform'],
                'last_seen_at' => now(),
            ])->save();

            return $device;
        });

        return Api::data([
            'device_identifier' => $device->device_identifier,
            'platform' => $device->platform,
            'last_seen_at' => Present::ts($device->last_seen_at),
        ]);
    }

    /** Idempotent: perangkat milik akun lain atau tidak terdaftar tetap 204 tanpa perubahan. */
    public function destroy(Request $request, string $deviceIdentifier): JsonResponse
    {
        PerangkatPush::where('device_identifier', $deviceIdentifier)->where('akun_id', $request->user()->id)->delete();

        return Api::noContent();
    }
}
