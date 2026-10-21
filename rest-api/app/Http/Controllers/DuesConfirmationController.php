<?php

namespace App\Http\Controllers;

use App\Domain\Finance\DuesConfirmations;
use App\Domain\Services\Photo;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\KonfirmasiIuran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Konfirmasi pembayaran iuran dengan foto bukti transfer (ADR35). Warga mengirim dan melihat miliknya; pengurus
 * melihat semua, membuka fotonya, lalu menandai selesai (setelah mencatat pembayaran) atau menolak dengan catatan.
 */
class DuesConfirmationController extends Controller
{
    /** POST /dues/confirmations — multipart: foto (wajib), periode[] (YYYY-MM, opsional), nominal, catatan. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'foto' => ['required', 'file', 'max:5120'],
            'periode' => ['sometimes', 'array', 'max:12'],
            'periode.*' => ['date_format:Y-m'],
            'nominal' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'catatan' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);
        $k = DuesConfirmations::submit($request->user(), $request->file('foto'), $data['periode'] ?? [], $data['nominal'] ?? null, $data['catatan'] ?? null);

        return Api::data(Present::konfirmasiIuran($k, false), 201);
    }

    /** GET /dues/confirmations — konfirmasi milik akun ini, terbaru dulu. */
    public function mine(Request $request): JsonResponse
    {
        $rows = KonfirmasiIuran::where('akun_id', $request->user()->id)->orderByDesc('created_at')->limit(20)->get();

        return Api::data($rows->map(fn ($k) => Present::konfirmasiIuran($k, false))->values());
    }

    /** GET /dues/confirmations/{id}/photo — pemilik atau pengurus; selain itu 404 (tidak membocorkan keberadaan). */
    public function photo(Request $request, string $id): StreamedResponse
    {
        $akun = $request->user();
        $k = KonfirmasiIuran::whereKey($id)->when(! $akun->isPengurus(), fn ($q) => $q->where('akun_id', $akun->id))->firstOrFail();

        return Storage::disk(Photo::DISK)->response($k->storage_key, 'bukti-transfer.jpg', [
            'Content-Type' => $k->mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /admin/dues-confirmations?status=diajukan|selesai|ditolak */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'diajukan');
        $q = KonfirmasiIuran::with(['akun', 'rumah'])
            ->when(in_array($status, ['diajukan', 'selesai', 'ditolak'], true), fn ($q) => $q->where('status', $status))
            ->orderBy($status === 'diajukan' ? 'created_at' : 'diproses_at', $status === 'diajukan' ? 'asc' : 'desc');

        return Api::paginate($request, $q, fn (KonfirmasiIuran $k) => Present::konfirmasiIuran($k, true));
    }

    /** POST /admin/dues-confirmations/{id}/process — status selesai|ditolak; catatan wajib saat menolak. */
    public function process(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:selesai,ditolak'],
            'catatan' => ['required_if:status,ditolak', 'nullable', 'string', 'max:300'],
        ]);
        $k = DuesConfirmations::process($request->user(), $id, $data['status'], $data['catatan'] ?? null);

        return Api::data(Present::konfirmasiIuran($k->load(['akun', 'rumah']), true));
    }
}
