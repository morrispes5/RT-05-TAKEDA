<?php

namespace App\Http\Controllers;

use App\Domain\Services\ActivityProposals;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\DokumenUsulan;
use App\Models\UsulanKegiatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Usulan kegiatan warga dan keputusan pengurus. Agenda resmi tetap tabel agenda. */
class ActivityProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = UsulanKegiatan::query()->with('dokumen')
            ->when(! $user->isPengurus(), fn ($q) => $q->where('pengusul_id', $user->id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at');

        return Api::paginate($request, $q, fn (UsulanKegiatan $u) => Present::usulan($u, $user));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $usulan = $this->visible($request, $id);

        return Api::data(Present::usulan($usulan->load(['dokumen', 'pengusul']), $request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $usulan = ActivityProposals::create($request->user(), $data, $request->file('dokumen', []));

        return Api::data(Present::usulan($usulan->load('dokumen'), $request->user()), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $usulan = $this->visible($request, $id);
        $data = $request->validate($this->rules() + ['versi' => ['required', 'integer']]);
        $updated = ActivityProposals::updateOwn($request->user(), $usulan, $data, $request->file('dokumen', []));

        return Api::data(Present::usulan($updated->load('dokumen'), $request->user()));
    }

    public function history(Request $request, string $id): JsonResponse
    {
        $usulan = $this->visible($request, $id)->load('riwayat.aktor');

        return Api::data($usulan->riwayat->map(fn ($r) => Present::riwayat($r, $request->user(), false, $usulan->pengusul_id))->values());
    }

    public function document(Request $request, string $id, string $documentId): StreamedResponse
    {
        $usulan = $this->visible($request, $id);
        $dokumen = DokumenUsulan::where('usulan_kegiatan_id', $usulan->id)->findOrFail($documentId);
        $ext = $dokumen->mime === 'application/pdf' ? 'pdf' : 'jpg';

        return Storage::disk(ActivityProposals::DISK)->response($dokumen->storage_key, 'dokumen-'.$dokumen->urutan.'.'.$ext, [
            'Content-Type' => $dokumen->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:ditinjau,perlu_revisi,disetujui,ditolak'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['required', 'integer'],
        ]);
        $usulan = UsulanKegiatan::findOrFail($id);
        $updated = ActivityProposals::changeStatus($request->user(), $usulan, $data['status'], $data['catatan'] ?? null, (int) $data['expected_version']);

        return Api::data(Present::usulan($updated->load('dokumen'), $request->user()));
    }

    public function schedule(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer']]);
        $usulan = UsulanKegiatan::findOrFail($id);
        $updated = ActivityProposals::schedule($request->user(), $usulan, (int) $data['expected_version']);

        return Api::data(Present::usulan($updated->load(['dokumen', 'agenda']), $request->user()));
    }

    private function visible(Request $request, string $id): UsulanKegiatan
    {
        $user = $request->user();

        return UsulanKegiatan::with('dokumen')
            ->when(! $user->isPengurus(), fn ($q) => $q->where('pengusul_id', $user->id))
            ->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:160'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'lokasi' => ['required', 'string', 'max:200'],
            'mulai_at' => ['required', 'date'],
            'selesai_at' => ['nullable', 'date', 'after_or_equal:mulai_at'],
            'dokumen' => ['array', 'max:3'],
            'dokumen.*' => ['file', 'max:8192'],
        ];
    }
}
