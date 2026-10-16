<?php

namespace App\Http\Controllers;

use App\Domain\Services\ActivityProposals;
use App\Exceptions\DomainConflict;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\DokumenUsulan;
use App\Models\UsulanKegiatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Usulan kegiatan warga dan keputusan pengurus. Agenda resmi tetap tabel agenda. */
class ActivityProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['sometimes', 'nullable', 'in:diajukan,ditinjau,perlu_revisi,disetujui,ditolak']]);
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
        $data = Present::usulan($usulan->load(['dokumen', 'pengusul']), $request->user());
        if ($request->user()->isPengurus()) {
            // Peringatan jadwal bentrok hanya untuk pengurus; bukan pemblokir persetujuan.
            $data['agenda_bentrok'] = ActivityProposals::overlappingAgendas($usulan)
                ->map(fn ($a) => ['id' => $a->id, 'nama' => $a->nama, 'lokasi' => $a->lokasi, 'mulai_at' => $a->mulai_at?->toIso8601String(), 'selesai_at' => $a->selesai_at?->toIso8601String()])
                ->values()->all();
        }

        return Api::data($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $usulan = ActivityProposals::create($request->user(), $data, $request->file('dokumen', []));

        return Api::data(Present::usulan($usulan->load(['dokumen', 'pengusul']), $request->user()), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $usulan = $this->visible($request, $id);
        $data = $request->validate($this->rules() + ['versi' => ['required', 'integer']], $this->messages());
        $updated = ActivityProposals::updateOwn($request->user(), $usulan, $data, $request->file('dokumen', []));

        return Api::data(Present::usulan($updated->load(['dokumen', 'pengusul']), $request->user()));
    }

    public function history(Request $request, string $id): JsonResponse
    {
        $usulan = $this->visible($request, $id)->load('riwayat.aktor');

        return Api::data($usulan->riwayat->map(fn ($r) => Present::riwayat($r, $request->user(), false, $usulan->pengusul_id))->values());
    }

    public function document(Request $request, string $id, string $documentId): StreamedResponse
    {
        $usulan = $this->visible($request, $id);

        return $this->stream(DokumenUsulan::where('usulan_kegiatan_id', $usulan->id)->findOrFail($documentId));
    }

    /** Tautan unduh sekali-pakai-singkat (5 menit, relatif) bagi pengusul/pengurus yang berhak. */
    public function documentLink(Request $request, string $id, string $documentId): JsonResponse
    {
        $usulan = $this->visible($request, $id);
        $dokumen = DokumenUsulan::where('usulan_kegiatan_id', $usulan->id)->findOrFail($documentId);
        $expires = now()->addMinutes(5);
        $url = URL::temporarySignedRoute('activity-proposals.document.signed', $expires, ['documentId' => $dokumen->id], absolute: false);

        return Api::data(['url' => $url, 'expires_at' => $expires->toIso8601String()]);
    }

    public function signedDocument(Request $request, string $documentId): StreamedResponse
    {
        if (! $request->hasValidRelativeSignature()) {
            throw new DomainConflict('INVALID_SIGNATURE', 'Tautan unduhan tidak sah atau sudah kedaluwarsa. Minta tautan baru dari aplikasi.', 403);
        }

        return $this->stream(DokumenUsulan::findOrFail($documentId));
    }

    private function stream(DokumenUsulan $dokumen): StreamedResponse
    {
        $ext = match ($dokumen->mime) {
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'jpg',
        };

        return Storage::disk(ActivityProposals::DISK)->response($dokumen->storage_key, 'dokumen-'.$dokumen->urutan.'.'.$ext, [
            'Content-Type' => $dokumen->mime === 'text/plain' ? 'text/plain; charset=utf-8' : $dokumen->mime,
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

        return Api::data(Present::usulan($updated->load(['dokumen', 'pengusul']), $request->user()));
    }

    private function visible(Request $request, string $id): UsulanKegiatan
    {
        $user = $request->user();

        return UsulanKegiatan::with('dokumen')
            ->when(! $user->isPengurus(), fn ($q) => $q->where('pengusul_id', $user->id))
            ->findOrFail($id);
    }

    /** Aturan form docs/USULAN_KEGIATAN.md §3. @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'jenis' => ['required', 'in:'.implode(',', ActivityProposals::JENIS)],
            'nama' => ['required', 'string', 'min:5', 'max:160'],
            'deskripsi' => ['required', 'string', 'min:20', 'max:5000'],
            'lokasi' => ['required', 'string', 'min:3', 'max:200'],
            'mulai_at' => ['required', 'date', 'after:'.now()->addDay()->subMinute()->toIso8601String(), 'before:'.now()->addDays(366)->toIso8601String()],
            'selesai_at' => ['required', 'date', 'after:mulai_at', function (string $attr, mixed $value, \Closure $fail) {
                $start = strtotime((string) request('mulai_at'));
                if ($start && strtotime((string) $value) > $start + 12 * 3600) {
                    $fail('Kegiatan paling lama 12 jam.');
                }
            }],
            'perkiraan_peserta' => ['nullable', 'integer', 'between:1,1000'],
            'kebutuhan' => ['nullable', 'string', 'max:1000'],
            'pernyataan' => ['accepted'],
            'dokumen' => ['array', 'max:3'],
            'dokumen.*' => ['file', 'max:8192'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'mulai_at.after' => 'Kegiatan diajukan paling cepat 24 jam sebelum mulai.',
            'mulai_at.before' => 'Tanggal kegiatan paling lambat satu tahun dari sekarang.',
            'selesai_at.after' => 'Jam selesai harus setelah jam mulai.',
            'pernyataan.accepted' => 'Centang pernyataan penanggung jawab kegiatan.',
            'deskripsi.min' => 'Jelaskan kegiatan minimal 20 karakter.',
        ];
    }
}
