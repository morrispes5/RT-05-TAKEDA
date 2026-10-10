<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Dues;
use App\Exceptions\DomainConflict;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\PembayaranIuran;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Models\TarifIuran;
use App\Models\TransaksiKas;
use App\Support\Audit;
use App\Support\Idempotency;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Iuran dan kas (FR12–FR17). Tidak ada endpoint bayar online/checkout/webhook.
 * "Belum dibayar" berarti belum dicatat pengurus; status berubah hanya setelah pencatatan sukses.
 */
class FinanceController extends Controller
{
    private const OFFLINE_NOTE = 'Iuran dibayar langsung kepada pengurus. Status berubah setelah pengurus mencatat pembayaran.';

    /** GET /dues/my — tagihan rumah akun (minimal 12 bulan terakhir), tunggakan dipisah dari bulan berjalan. */
    public function my(Request $request): JsonResponse
    {
        $rumah = $request->user()->rumah();
        if (! $rumah) {
            throw new DomainConflict('HOUSEHOLD_NOT_LINKED', 'Akun belum terhubung dengan rumah.', 404);
        }

        return Api::data($this->houseSummary($rumah, (int) $request->query('bulan', 24)));
    }

    private function houseSummary(Rumah $rumah, int $months): array
    {
        $months = max(12, min(60, $months));
        $current = CarbonImmutable::now('Asia/Jakarta')->startOfMonth()->format('Y-m-01');
        $charges = TagihanIuran::with('alokasi')->where('rumah_id', $rumah->id)
            ->where('periode', '>=', CarbonImmutable::parse($current)->subMonths($months - 1)->toDateString())
            ->orderByDesc('periode')->get();
        $all = TagihanIuran::where('rumah_id', $rumah->id)->where('status', 'belum_bayar');

        return [
            'rumah' => ['id' => $rumah->id, 'kode_rumah' => $rumah->kode_rumah, 'alamat' => $rumah->alamat()],
            'tarif_berlaku' => TarifIuran::where('mulai_periode', '<=', $current)->orderByDesc('mulai_periode')->value('nominal'),
            'tunggakan' => [
                'jumlah_bulan' => (clone $all)->where('periode', '<', $current)->count(),
                'total' => (int) (clone $all)->where('periode', '<', $current)->sum('nominal_tagihan'),
            ],
            'bulan_berjalan' => ($c = $charges->firstWhere(fn ($t) => $t->periode->format('Y-m-01') === $current)) ? Present::tagihan($c) : null,
            'tagihan' => $charges->map(fn ($t) => Present::tagihan($t))->values()->all(),
            'catatan' => self::OFFLINE_NOTE,
        ];
    }

    /** GET /dues/transparency — kode rumah + status per periode, tanpa nama/kontak/anggota (BR15). */
    public function transparency(Request $request): JsonResponse
    {
        $period = Dues::period($request->query('periode', CarbonImmutable::now('Asia/Jakarta')->format('Y-m')));
        $rows = TagihanIuran::join('rumah', 'rumah.id', '=', 'tagihan_iuran.rumah_id')
            ->where('tagihan_iuran.periode', $period->toDateString())
            ->orderBy('rumah.kode_rumah')
            ->get(['rumah.kode_rumah', 'tagihan_iuran.nominal_tagihan', 'tagihan_iuran.status']);

        return Api::data([
            'periode' => $period->format('Y-m'),
            'jumlah_rumah' => $rows->count(),
            'lunas' => $rows->where('status', 'lunas')->count(),
            'belum_bayar' => $rows->where('status', 'belum_bayar')->count(),
            'rumah' => $rows->map(fn ($r) => [
                'kode_rumah' => $r->kode_rumah,
                'nominal' => (int) $r->nominal_tagihan,
                'status' => $r->status,
                'status_label' => $r->status === 'lunas' ? 'Lunas' : 'Belum dibayar',
            ])->values(),
            'catatan' => self::OFFLINE_NOTE,
        ]);
    }

    /** GET /cash/summary — saldo dan mutasi tersanitasi untuk warga aktif. */
    public function cashSummary(Request $request): JsonResponse
    {
        $detail = $request->user()->isPengurus();
        $month = CarbonImmutable::now('Asia/Jakarta')->startOfMonth();

        return Api::data([
            'saldo' => Dues::balance(),
            'bulan_ini' => [
                'pemasukan' => (int) TransaksiKas::where('tanggal', '>=', $month->toDateString())->where('nominal', '>', 0)->sum('nominal'),
                'pengeluaran' => (int) -TransaksiKas::where('tanggal', '>=', $month->toDateString())->where('nominal', '<', 0)->sum('nominal'),
            ],
            'mutasi_terakhir' => TransaksiKas::orderByDesc('tanggal')->orderByDesc('created_at')->limit(30)->get()
                ->map(fn ($k) => Present::kas($k, $detail))->values(),
        ]);
    }

    public function rates(): JsonResponse
    {
        return Api::data(TarifIuran::orderByDesc('mulai_periode')->get()->map(fn ($t) => [
            'id' => $t->id, 'nominal' => $t->nominal, 'mulai_periode' => $t->mulai_periode->format('Y-m'), 'alasan' => $t->alasan,
            'created_at' => Present::ts($t->created_at),
        ]));
    }

    /** POST /admin/dues-rates — tarif baru untuk periode mendatang; tagihan lama tidak berubah. */
    public function storeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nominal' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'mulai_periode' => ['required', 'date_format:Y-m'],
            'alasan' => ['required', 'string', 'max:300'],
        ]);
        $period = Dues::period($data['mulai_periode']);
        if (TagihanIuran::where('periode', '>=', $period->toDateString())->exists()) {
            throw new DomainConflict('RATE_PERIOD_HAS_CHARGES', 'Periode tersebut sudah memiliki tagihan. Tetapkan tarif untuk periode berikutnya.', 422);
        }
        $rate = DB::transaction(function () use ($request, $data, $period) {
            $r = TarifIuran::create(['nominal' => $data['nominal'], 'mulai_periode' => $period->toDateString(), 'alasan' => $data['alasan'], 'dibuat_oleh_id' => $request->user()->id]);
            Audit::record($request->user(), 'tarif_iuran.dibuat', 'tarif_iuran', $r->id, ['nominal' => $r->nominal, 'mulai' => $data['mulai_periode']]);

            return $r;
        });

        return Api::data(['id' => $rate->id, 'nominal' => $rate->nominal, 'mulai_periode' => $data['mulai_periode']], 201);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate(['periode' => ['nullable', 'date_format:Y-m']]);
        $created = isset($data['periode'])
            ? Dues::generate(Dues::period($data['periode']), $request->user())
            : Dues::catchUp($request->user());

        return Api::data(['tagihan_baru' => $created]);
    }

    public function charges(Request $request): JsonResponse
    {
        $q = TagihanIuran::with(['rumah', 'alokasi'])
            ->when($request->query('periode'), fn ($q, $p) => $q->where('periode', Dues::period($p)->toDateString()))
            ->when($request->query('rumah_id'), fn ($q, $r) => $q->where('rumah_id', $r))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('periode');

        return Api::paginate($request, $q, fn (TagihanIuran $t) => Present::tagihan($t) + ['rumah' => ['id' => $t->rumah->id, 'kode_rumah' => $t->rumah->kode_rumah]]);
    }

    public function houseDues(string $id): JsonResponse
    {
        return Api::data($this->houseSummary(Rumah::findOrFail($id), 24));
    }

    /** POST /admin/dues-payments — catat pembayaran offline (Idempotency-Key wajib). */
    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rumah_id' => ['required', 'uuid'],
            'periode' => ['required', 'array', 'min:1', 'max:24'],
            'periode.*' => ['required', 'date_format:Y-m'],
            'nominal' => ['required', 'integer', 'min:1'],
            'tanggal_bayar' => ['required', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        return Idempotency::run($request, $request->user(), 'dues-payments.store', $data, fn () => Api::data(Present::pembayaran(
            Dues::recordPayment($request->user(), $data['rumah_id'], $data['periode'], (int) $data['nominal'], $data['tanggal_bayar'], $data['catatan'] ?? null)
        ), 201));
    }

    public function payments(Request $request): JsonResponse
    {
        $q = PembayaranIuran::with(['rumah', 'alokasi.tagihan'])
            ->when($request->query('rumah_id'), fn ($q, $r) => $q->where('rumah_id', $r))
            ->orderByDesc('created_at');

        return Api::paginate($request, $q, fn (PembayaranIuran $p) => Present::pembayaran($p));
    }

    public function payment(string $id): JsonResponse
    {
        return Api::data(Present::pembayaran(PembayaranIuran::with(['rumah', 'alokasi.tagihan'])->findOrFail($id)));
    }

    public function reversePayment(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'dues-payments.reverse', $data + ['id' => $id], fn () => Api::data(Present::pembayaran(
            Dues::reversePayment($request->user(), $id, $data['alasan'])
        )));
    }

    public function correctPayment(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:300'],
            'periode' => ['required', 'array', 'min:1', 'max:24'],
            'periode.*' => ['required', 'date_format:Y-m'],
            'nominal' => ['required', 'integer', 'min:1'],
            'tanggal_bayar' => ['required', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        return Idempotency::run($request, $request->user(), 'dues-payments.correct', $data + ['id' => $id], fn () => Api::data(Present::pembayaran(
            Dues::correctPayment($request->user(), $id, $data['alasan'], $data['periode'], (int) $data['nominal'], $data['tanggal_bayar'], $data['catatan'] ?? null)
        ), 201));
    }

    public function cash(Request $request): JsonResponse
    {
        $q = TransaksiKas::query()
            ->when($request->query('jenis'), fn ($q, $j) => $q->where('jenis', $j))
            ->orderByDesc('tanggal')->orderByDesc('created_at');

        $response = Api::paginate($request, $q, fn (TransaksiKas $k) => Present::kas($k, true));
        $body = $response->getData(true);
        $body['meta']['saldo'] = Dues::balance();

        return $response->setData($body);
    }

    public function storeCash(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:pemasukan,pengeluaran,saldo_awal'],
            'nominal' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'kategori' => ['required', 'string', 'max:60'],
            'keterangan' => ['required', 'string', 'max:500'],
        ]);

        return Idempotency::run($request, $request->user(), 'cash.store', $data, function () use ($request, $data) {
            $entry = Dues::recordCash($request->user(), $data['jenis'], (int) $data['nominal'], $data['tanggal'], $data['kategori'], $data['keterangan']);
            $warning = Dues::balance() < 0 ? 'Saldo kas menjadi negatif setelah pencatatan ini.' : null;

            return Api::data(Present::kas($entry, true), 201, array_filter(['peringatan' => $warning]));
        });
    }

    public function reverseCash(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'cash.reverse', $data + ['id' => $id], fn () => Api::data(Present::kas(
            Dues::reverseCash($request->user(), $id, $data['alasan']), true
        ), 201));
    }
}
