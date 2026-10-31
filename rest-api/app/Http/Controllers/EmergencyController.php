<?php

namespace App\Http\Controllers;

use App\Domain\Community\Emergencies;
use App\Http\Responses\Api;
use App\Models\KejadianDarurat;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmergencyController extends Controller
{
    public function status(Request $r): JsonResponse
    {
        $s = Emergencies::setting();

        return Api::data(['tersedia' => (bool) config('panic.enabled'), 'aktif' => Emergencies::enabled(),
            'versi' => (int) $s->version, 'penyalahgunaan' => Emergencies::misuseCount($r->user()->id),
            'versi_ketentuan' => Emergencies::TERMS, 'uji_terbatas' => config('panic.audience') !== 'all'])->header('Cache-Control', 'no-store');
    }

    private function visible(Request $r)
    {
        $actor = Emergencies::authorize($r->user());
        $q = KejadianDarurat::query();
        if (! $actor->isPengurus()) {
            // Residents see recent community emergencies and their own history, never a resident directory.
            $q->where(fn ($q) => $q->where('pelapor_id', $actor->id)
                ->orWhere(fn ($q) => $q->where('created_at', '>=', now()->subDay())->where('created_at', '>=', $actor->created_at)));
            if (config('panic.audience') !== 'all' && ! in_array($actor->id, config('panic.recipient_ids', []), true)) {
                $q->where('pelapor_id', $actor->id);
            }
        }

        return [$actor, $q->with(['pelapor', 'rumah'])];
    }

    public function index(Request $r): JsonResponse
    {
        [$actor, $q] = $this->visible($r);

        return Api::paginate($r, $q->orderByDesc('created_at'),
            fn ($e) => Emergencies::present($e, $actor->isPengurus()))->header('Cache-Control', 'no-store');
    }

    public function show(Request $r, string $id): JsonResponse
    {
        [$actor, $q] = $this->visible($r);

        $e = $q->findOrFail($id);

        // milik_saya: status pengiriman pemberitahuan hanya relevan bagi pelapor (dan pengurus), bukan penerima alarm.
        return Api::data(Emergencies::present($e, $actor->isPengurus())
            + ['pemberitahuan' => Emergencies::deliveryState($id), 'milik_saya' => $e->pelapor_id === $actor->id])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $r): JsonResponse
    {
        Emergencies::authorize($r->user());
        $d = $r->validate(['jenis' => ['nullable', Rule::in(Emergencies::KINDS)], 'konfirmasi' => ['required', 'accepted'],
            'versi_ketentuan' => ['required', Rule::in(Emergencies::ACCEPTED_TERMS)]]);

        $receipt = Idempotency::run($r, $r->user(), 'darurat.buat', $d,
            fn () => Api::data(['id' => Emergencies::create($r->user(), $d['jenis'] ?? null)->id], 201));

        return $this->hydrate($r, $receipt);
    }

    public function setKind(Request $r, string $id): JsonResponse
    {
        Emergencies::authorize($r->user());
        $d = $r->validate(['versi' => ['required', 'integer', 'min:1'], 'jenis' => ['required', Rule::in(Emergencies::KINDS)]]);

        $receipt = Idempotency::run($r, $r->user(), 'darurat.jenis.'.$id, $d,
            fn () => Api::data(['id' => Emergencies::setKind($r->user(), $id, $d['versi'], $d['jenis'])->id]));

        return $this->hydrate($r, $receipt);
    }

    public function cancel(Request $r, string $id): JsonResponse
    {
        Emergencies::authorize($r->user());
        $d = $r->validate(['versi' => ['required', 'integer', 'min:1']]);

        $receipt = Idempotency::run($r, $r->user(), 'darurat.batal.'.$id, $d,
            fn () => Api::data(['id' => Emergencies::cancelByReporter($r->user(), $id, $d['versi'])->id]));

        return $this->hydrate($r, $receipt);
    }

    public function resolve(Request $r, string $id): JsonResponse
    {
        Emergencies::authorize($r->user(), true);
        $d = $r->validate(['versi' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['selesai', 'palsu'])],
            'penyalahgunaan' => ['required', 'boolean'], 'alasan' => ['required', 'string', 'min:3', 'max:1000']]);

        $receipt = Idempotency::run($r, $r->user(), 'darurat.tangani.'.$id, $d,
            fn () => Api::data(['id' => Emergencies::resolve($r->user(), $id, $d['versi'], $d['status'], $d['penyalahgunaan'], $d['alasan'])->id]));

        return $this->hydrate($r, $receipt);
    }

    public function correctMisuse(Request $r, string $id): JsonResponse
    {
        Emergencies::authorize($r->user(), true);
        $d = $r->validate(['alasan' => ['required', 'string', 'min:5', 'max:300']]);

        $receipt = Idempotency::run($r, $r->user(), 'darurat.koreksi.'.$id, $d,
            fn () => Api::data(['id' => Emergencies::correctMisuse($r->user(), $id, $d['alasan'])->id]));

        return $this->hydrate($r, $receipt);
    }

    /** Cache only the mutation reference. Replays must not preserve identities after anonymisation. */
    private function hydrate(Request $r, JsonResponse $receipt): JsonResponse
    {
        $id = $receipt->getData(true)['data']['id'];
        [$actor, $q] = $this->visible($r);
        $response = Api::data(Emergencies::present($q->findOrFail($id), $actor->isPengurus()) + ['pemberitahuan' => Emergencies::deliveryState($id)], $receipt->getStatusCode())
            ->header('Cache-Control', 'no-store');
        if ($receipt->headers->has('Idempotent-Replayed')) {
            $response->header('Idempotent-Replayed', 'true');
        }

        return $response;
    }

    public function configure(Request $r): JsonResponse
    {
        Emergencies::authorize($r->user(), true);
        $d = $r->validate(['versi' => ['required', 'integer', 'min:1'], 'aktif' => ['required', 'boolean']]);

        return Idempotency::run($r, $r->user(), 'darurat.fitur', $d, function () use ($r, $d) {
            Emergencies::configure($r->user(), $d['versi'], $d['aktif']);

            return $this->status($r);
        })->header('Cache-Control', 'no-store');
    }
}
