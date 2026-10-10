<?php

namespace App\Http\Controllers;

use App\Domain\Services\Photo;
use App\Domain\Services\Reports;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\Aspirasi;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use App\Models\Pengaduan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengaduan dan aspirasi warga (BR08: dapat dibaca warga terdaftar, tersanitasi).
 * Arsip tidak tampil untuk warga; pengurus dapat menyertakan arsip dengan ?arsip=1.
 */
class ReportsController extends Controller
{
    public function categories(): JsonResponse
    {
        return Api::data(KategoriPengaduan::where('aktif', true)->orderBy('nama')->get(['id', 'nama']));
    }

    public function complaints(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $q = Pengaduan::with(['kategori', 'pelapor', 'foto'])
            ->when(! ($viewer->isPengurus() && $request->boolean('arsip')), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('milik_saya'), fn ($q) => $q->where('pelapor_id', $viewer->id))
            ->when($request->query('kategori_id'), fn ($q, $k) => $q->where('kategori_id', $k))
            ->orderByDesc('created_at');

        return Api::paginate($request, $q, fn (Pengaduan $p) => Present::pengaduan($p, $viewer));
    }

    public function storeComplaint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kategori_id' => ['required', 'uuid'],
            'judul' => ['required', 'string', 'max:150'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'sembunyikan_identitas' => ['boolean'],
            'foto' => ['array', 'max:5'],
            'foto.*' => ['file', 'max:12288'],
        ]);
        $p = Reports::createComplaint($request->user(), $data, $request->file('foto', []));

        return Api::data(Present::pengaduan($p->load(['kategori', 'pelapor', 'foto']), $request->user()), 201);
    }

    private function visibleComplaint(Request $request, string $id): Pengaduan
    {
        $viewer = $request->user();

        return Pengaduan::with(['kategori', 'pelapor', 'foto', 'riwayat.aktor'])
            ->when(! $viewer->isPengurus(), fn ($q) => $q->whereNull('archived_at'))
            ->findOrFail($id);
    }

    public function showComplaint(Request $request, string $id): JsonResponse
    {
        return Api::data(Present::pengaduan($this->visibleComplaint($request, $id), $request->user()));
    }

    public function updateComplaint(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'versi' => ['required', 'integer'],
            'judul' => ['sometimes', 'string', 'max:150'],
            'deskripsi' => ['sometimes', 'string', 'max:5000'],
            'sembunyikan_identitas' => ['sometimes', 'boolean'],
        ]);
        $p = Reports::updateOwnComplaint($request->user(), $this->visibleComplaint($request, $id), $data);

        return Api::data(Present::pengaduan($p->load(['kategori', 'pelapor', 'foto']), $request->user()));
    }

    public function complaintHistory(Request $request, string $id): JsonResponse
    {
        $p = $this->visibleComplaint($request, $id);
        $viewer = $request->user();
        $hideOwner = $p->sembunyikan_identitas && ! $viewer->isPengurus() && $p->pelapor_id !== $viewer->id;

        return Api::data($p->riwayat->map(fn ($r) => Present::riwayat($r, $viewer, $hideOwner))->values());
    }

    /** Foto privat: hanya akun aktif yang boleh melihat laporan; tidak ada URL publik. */
    public function complaintPhoto(Request $request, string $id, string $photoId): StreamedResponse
    {
        $p = $this->visibleComplaint($request, $id);
        $foto = FotoPengaduan::where('pengaduan_id', $p->id)->findOrFail($photoId);

        return Storage::disk(Photo::DISK)->response($foto->storage_key, 'foto-'.$foto->urutan.'.jpg', [
            'Content-Type' => $foto->mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function changeComplaintStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:diajukan,diproses,selesai'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['required', 'integer'],
        ]);
        $p = Reports::changeComplaintStatus($request->user(), Pengaduan::findOrFail($id), $data['status'], $data['catatan'] ?? null, $data['expected_version']);

        return Api::data(Present::pengaduan($p->load(['kategori', 'pelapor', 'foto']), $request->user()));
    }

    public function archiveComplaint(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);
        Reports::archive($request->user(), Pengaduan::findOrFail($id), $data['alasan']);

        return Api::noContent();
    }

    public function aspirations(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $q = Aspirasi::with('pengirim')
            ->when(! ($viewer->isPengurus() && $request->boolean('arsip')), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('milik_saya'), fn ($q) => $q->where('pengirim_id', $viewer->id))
            ->orderByDesc('created_at');

        return Api::paginate($request, $q, fn (Aspirasi $a) => Present::aspirasi($a, $viewer));
    }

    public function storeAspiration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string', 'max:5000'],
            'sembunyikan_identitas' => ['boolean'],
        ]);
        $a = Reports::createAspiration($request->user(), $data);

        return Api::data(Present::aspirasi($a->load('pengirim'), $request->user()), 201);
    }

    private function visibleAspiration(Request $request, string $id): Aspirasi
    {
        return Aspirasi::with(['pengirim', 'riwayat.aktor'])
            ->when(! $request->user()->isPengurus(), fn ($q) => $q->whereNull('archived_at'))
            ->findOrFail($id);
    }

    public function showAspiration(Request $request, string $id): JsonResponse
    {
        return Api::data(Present::aspirasi($this->visibleAspiration($request, $id), $request->user()));
    }

    public function updateAspiration(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'versi' => ['required', 'integer'],
            'judul' => ['sometimes', 'string', 'max:150'],
            'isi' => ['sometimes', 'string', 'max:5000'],
            'sembunyikan_identitas' => ['sometimes', 'boolean'],
        ]);
        $a = Reports::updateOwnAspiration($request->user(), $this->visibleAspiration($request, $id), $data);

        return Api::data(Present::aspirasi($a->load('pengirim'), $request->user()));
    }

    public function aspirationHistory(Request $request, string $id): JsonResponse
    {
        $a = $this->visibleAspiration($request, $id);
        $viewer = $request->user();
        $hideOwner = $a->sembunyikan_identitas && ! $viewer->isPengurus() && $a->pengirim_id !== $viewer->id;

        return Api::data($a->riwayat->map(fn ($r) => Present::riwayat($r, $viewer, $hideOwner))->values());
    }

    public function changeAspirationStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:diajukan,ditinjau,selesai'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['required', 'integer'],
        ]);
        $a = Reports::changeAspirationStatus($request->user(), Aspirasi::findOrFail($id), $data['status'], $data['catatan'] ?? null, $data['expected_version']);

        return Api::data(Present::aspirasi($a->load('pengirim'), $request->user()));
    }

    public function archiveAspiration(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);
        Reports::archive($request->user(), Aspirasi::findOrFail($id), $data['alasan']);

        return Api::noContent();
    }
}
