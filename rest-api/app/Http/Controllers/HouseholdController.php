<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainConflict;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\Akun;
use App\Models\Keluarga;
use App\Models\Warga;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Data keluarga sendiri (BR05, ADR10): semua akun aktif membaca keluarganya; hanya akun utama
 * (atau pengurus) yang mengubah. Warga tidak pernah membaca keluarga lain (404 bila di luar scope).
 */
class HouseholdController extends Controller
{
    private function keluargaOf(Akun $akun): Keluarga
    {
        $keluarga = $akun->warga?->keluarga;
        if (! $keluarga || $keluarga->archived_at) {
            throw new DomainConflict('HOUSEHOLD_NOT_LINKED', 'Akun belum terhubung dengan keluarga.', 404);
        }

        return $keluarga->load(['warga' => fn ($q) => $q->with('akun'), 'penghunianAktif.rumah']);
    }

    private function assertCanEdit(Akun $akun): void
    {
        if (! $akun->isUtama() && ! $akun->isPengurus()) {
            throw new DomainConflict('PRIMARY_ACCOUNT_REQUIRED', 'Hanya akun utama yang dapat mengubah data keluarga.', 403);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $k = $this->keluargaOf($request->user());

        return Api::data(Present::keluarga($k, $k->penghunianAktif?->rumah));
    }

    /** PATCH /household — ubah WhatsApp kepala keluarga dengan optimistic version. */
    public function update(Request $request): JsonResponse
    {
        $akun = $request->user();
        $this->assertCanEdit($akun);
        $data = $request->validate([
            'versi' => ['required', 'integer'],
            'whatsapp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+ -]{8,25}$/'],
            'nama_kepala' => ['sometimes', 'string', 'max:120'],
        ]);
        $k = $this->keluargaOf($akun);

        DB::transaction(function () use ($akun, $k, $data) {
            $locked = Keluarga::whereKey($k->id)->lockForUpdate()->first();
            if ($locked->versi !== (int) $data['versi']) {
                throw new DomainConflict('VERSION_CONFLICT', 'Data keluarga sudah berubah. Muat ulang lalu coba lagi.');
            }
            $kepala = Warga::where('keluarga_id', $k->id)->where('is_kepala', true)->whereNull('archived_at')->first();
            if ($kepala) {
                if (array_key_exists('whatsapp', $data)) {
                    $kepala->whatsapp = $data['whatsapp'];
                }
                if (array_key_exists('nama_kepala', $data)) {
                    $kepala->nama = $data['nama_kepala'];
                }
                $kepala->save();
            }
            $locked->increment('versi');
            Audit::record($akun, 'keluarga.diubah', 'keluarga', $k->id, ['field' => array_keys(array_diff_key($data, ['versi' => 1]))]);
        });

        return $this->show($request);
    }

    public function addMember(Request $request): JsonResponse
    {
        $akun = $request->user();
        $this->assertCanEdit($akun);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'hubungan_keluarga' => ['required', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+ -]{8,25}$/'],
        ]);
        $k = $this->keluargaOf($akun);
        $warga = DB::transaction(function () use ($akun, $k, $data) {
            $w = Warga::create($data + ['keluarga_id' => $k->id, 'is_kepala' => false]);
            Keluarga::whereKey($k->id)->increment('versi');
            Audit::record($akun, 'warga.ditambah', 'warga', $w->id);

            return $w;
        });

        return Api::data(Present::warga($warga), 201);
    }

    public function updateMember(Request $request, string $id): JsonResponse
    {
        $akun = $request->user();
        $this->assertCanEdit($akun);
        $k = $this->keluargaOf($akun);
        $warga = Warga::where('keluarga_id', $k->id)->whereNull('archived_at')->findOrFail($id);
        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:120'],
            'hubungan_keluarga' => ['sometimes', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+ -]{8,25}$/'],
        ]);
        DB::transaction(function () use ($akun, $warga, $data, $k) {
            $warga->fill($data)->save();
            Keluarga::whereKey($k->id)->increment('versi');
            Audit::record($akun, 'warga.diubah', 'warga', $warga->id, ['field' => array_keys($data)]);
        });

        return Api::data(Present::warga($warga->refresh()));
    }

    /** DELETE /household/members/{id} — arsip keanggotaan, bukan hapus permanen (BR18). */
    public function endMember(Request $request, string $id): JsonResponse
    {
        $akun = $request->user();
        $this->assertCanEdit($akun);
        $k = $this->keluargaOf($akun);
        $warga = Warga::where('keluarga_id', $k->id)->whereNull('archived_at')->findOrFail($id);
        if ($warga->is_kepala || $warga->akun) {
            throw new DomainConflict('MEMBER_PROTECTED', 'Kepala keluarga atau anggota yang memiliki akun diubah melalui pengurus.', 422);
        }
        $request->validate(['status' => ['sometimes', 'in:pindah,meninggal']]);
        DB::transaction(function () use ($akun, $warga, $request, $k) {
            $warga->forceFill(['archived_at' => now(), 'status' => $request->input('status', 'pindah')])->save();
            Keluarga::whereKey($k->id)->increment('versi');
            Audit::record($akun, 'warga.diarsipkan', 'warga', $warga->id);
        });

        return Api::noContent();
    }
}
