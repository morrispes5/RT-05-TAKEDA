<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $v2 = (bool) config('finance.v2');
        if ($v2) {
            $all = DB::table('rt05_tagihan_saldo')->where('rumah_id', $rumah->id)->where('sisa', '>', 0);
        }
        $rate = TarifIuran::whereNull('dibatalkan_at')->where('mulai_periode', '<=', $current)->orderByDesc('mulai_periode')->value('nominal');
        $special = $v2 ? DB::table('tarif_rumah')->where('rumah_id', $rumah->id)->whereNull('dibatalkan_at')->where('mulai_periode', '<=', $current)->orderByDesc('mulai_periode')->first() : null;

        return [
            'rumah' => ['id' => $rumah->id, 'kode_rumah' => $rumah->kode_rumah, 'alamat' => $rumah->alamat()],
            'tarif_berlaku' => $special?->mode === 'khusus' ? (int) $special->nominal : $rate,
            ...($v2 ? ['saldo_titipan' => (int) DB::table('rt05_titipan_saldo')->where('rumah_id', $rumah->id)->sum('saldo'), 'versi_keuangan' => 2] : []),
            'tunggakan' => [
                'jumlah_bulan' => (clone $all)->where('periode', '<', $current)->count(),
                'total' => (int) (clone $all)->where('periode', '<', $current)->sum($v2 ? 'sisa' : 'nominal_tagihan'),
            ],
            'bulan_berjalan' => ($c = $charges->firstWhere(fn ($t) => $t->periode->format('Y-m-01') === $current)) ? Present::tagihan($c) : null,
            'tagihan' => $charges->map(fn ($t) => Present::tagihan($t))->values()->all(),
            'catatan' => self::OFFLINE_NOTE,
        ];
    }

    /**
     * GET /dues/transparency — kode rumah + status per periode, tanpa nama/kontak/anggota (BR15).
     * ADR35 (permintaan Pak RT): khusus pengurus; warga hanya melihat tagihan rumahnya sendiri (/dues/my).
     */
    public function transparency(Request $request): JsonResponse
    {
        if (! $request->user()->isPengurus()) {
            throw new AuthorizationException('Transparansi iuran hanya untuk pengurus.');
        }
        if (config('finance.v2')) {
            return app(FlexibleFinanceController::class)->recap($request);
        }
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

    /** GET /admin/cash/summary — saldo dan mutasi kas, khusus pengurus (ADR26; warga tidak melihat kas). */
    public function cashSummary(): JsonResponse
    {
        $month = CarbonImmutable::now('Asia/Jakarta')->startOfMonth();
        $reversed = self::reversedIds();

        return Api::data([
            'saldo' => Dues::balance(),
            'bulan_ini' => [
                'pemasukan' => (int) TransaksiKas::where('tanggal', '>=', $month->toDateString())->where('nominal', '>', 0)->sum('nominal'),
                'pengeluaran' => (int) -TransaksiKas::where('tanggal', '>=', $month->toDateString())->where('nominal', '<', 0)->sum('nominal'),
            ],
            'mutasi_terakhir' => TransaksiKas::orderByDesc('tanggal')->orderByDesc('created_at')->limit(30)->get()
                ->map(fn ($k) => Present::kas($k, isset($reversed[$k->id])))->values(),
        ]);
    }

    /** @return array<string, int> id transaksi kas yang sudah punya pembalikan */
    private static function reversedIds(): array
    {
        return TransaksiKas::whereNotNull('membalik_id')->pluck('membalik_id')->flip()->all();
    }

    public function rates(): JsonResponse
    {
        return Api::data(TarifIuran::orderByDesc('mulai_periode')->get()->map(fn ($t) => self::rate($t))->values());
    }

    /** POST /admin/dues-rates — tarif baru untuk periode mendatang; tagihan lama tidak berubah. */
    public function storeRate(Request $request): JsonResponse
    {
        if (config('finance.v2')) {
            return app(FlexibleFinanceController::class)->storeCommonRate($request);
        }
        $data = $request->validate([
            'nominal' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'mulai_periode' => ['required', 'date_format:Y-m'],
            'alasan' => ['required', 'string', 'max:300'],
        ]);
        $period = Dues::period($data['mulai_periode']);
        if (TagihanIuran::where('periode', '>=', $period->toDateString())->exists()) {
            throw new DomainConflict('RATE_PERIOD_HAS_CHARGES', 'Periode tersebut sudah memiliki tagihan. Tetapkan tarif untuk periode berikutnya.', 422);
        }
        $exists = fn () => new DomainConflict('RATE_PERIOD_EXISTS', 'Tarif untuk periode itu sudah ditetapkan. Pilih periode lain.', 409);
        if (TarifIuran::where('mulai_periode', $period->toDateString())->exists()) {
            throw $exists();
        }
        try {
            $rate = DB::transaction(function () use ($request, $data, $period) {
                $r = TarifIuran::create(['nominal' => $data['nominal'], 'mulai_periode' => $period->toDateString(), 'alasan' => $data['alasan'], 'dibuat_oleh_id' => $request->user()->id]);
                Audit::record($request->user(), 'tarif_iuran.dibuat', 'tarif_iuran', $r->id, ['nominal' => $r->nominal, 'mulai' => $data['mulai_periode']]);

                return $r;
            });
        } catch (UniqueConstraintViolationException) {
            throw $exists(); // dua pengurus menyimpan periode yang sama bersamaan
        }

        return Api::data(self::rate($rate->refresh()), 201);
    }

    private static function rate(TarifIuran $t): array
    {
        return [
            'id' => $t->id, 'nominal' => $t->nominal, 'mulai_periode' => $t->mulai_periode->format('Y-m'), 'alasan' => $t->alasan,
            'created_at' => Present::ts($t->created_at),
            ...(config('finance.v2') ? ['dibatalkan_at' => $t->dibatalkan_at, 'menggantikan_id' => $t->menggantikan_id] : []),
        ];
    }

    /**
     * GET /admin/dues-recap — rekap bulanan pengurus (M11): jumlah dan nominal lunas/belum per periode,
     * per rumah dengan jumlah bulan tunggakan sebelum periode itu. Hanya kode rumah, tanpa nama.
     */
    public function recap(Request $request): JsonResponse
    {
        if (config('finance.v2')) {
            return app(FlexibleFinanceController::class)->recap($request);
        }
        $period = Dues::period((string) $request->query('periode', CarbonImmutable::now('Asia/Jakarta')->format('Y-m')));
        $date = $period->toDateString();
        $charges = TagihanIuran::with(['rumah:id,kode_rumah', 'alokasi' => fn ($q) => $q->whereNull('dibatalkan_at')->with('pembayaran:id,tanggal_bayar')])
            ->where('periode', $date)->get()->sortBy(fn ($t) => $t->rumah->kode_rumah)->values();
        $arrears = TagihanIuran::where('periode', '<', $date)->where('status', 'belum_bayar')
            ->selectRaw('rumah_id, count(*) AS bulan, sum(nominal_tagihan) AS total')->groupBy('rumah_id')->get()->keyBy('rumah_id');
        $sum = fn (string $status) => [
            'rumah' => $charges->where('status', $status)->count(),
            'nominal' => (int) $charges->where('status', $status)->sum('nominal_tagihan'),
        ];

        return Api::data([
            'periode' => $period->format('Y-m'),
            'tarif' => TarifIuran::where('mulai_periode', '<=', $date)->orderByDesc('mulai_periode')->value('nominal'),
            'jumlah_rumah' => $charges->count(),
            'lunas' => $sum('lunas'),
            'belum_bayar' => $sum('belum_bayar'),
            'tunggakan_sebelumnya' => ['rumah' => $arrears->count(), 'nominal' => (int) $arrears->sum('total')],
            'rumah' => $charges->map(fn (TagihanIuran $t) => [
                'rumah_id' => $t->rumah_id,
                'kode_rumah' => $t->rumah->kode_rumah,
                'nominal' => $t->nominal_tagihan,
                'status' => $t->status,
                'status_label' => $t->status === 'lunas' ? 'Lunas' : 'Belum dibayar',
                'tanggal_bayar' => $t->alokasi->first()?->pembayaran?->tanggal_bayar?->toDateString(),
                'bulan_tunggakan_sebelumnya' => (int) ($arrears[$t->rumah_id]->bulan ?? 0),
            ])->values(),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate(['periode' => ['nullable', 'date_format:Y-m']]);
        if (config('finance.v2')) {
            return Idempotency::run($request, $request->user(), 'dues.generate', $data, fn () => Api::data([
                'tagihan_baru' => isset($data['periode']) ? Dues::generate(Dues::period($data['periode']), $request->user()) : Dues::catchUp($request->user()),
            ]));
        }
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
        if (config('finance.v2')) {
            return app(FlexibleFinanceController::class)->storePayment($request);
        }
        $data = $request->validate([
            'rumah_id' => ['required', 'uuid'],
            'periode' => ['required', 'array', 'min:1', 'max:24'],
            'periode.*' => ['required', 'date_format:Y-m'],
            'nominal' => ['required', 'integer', 'min:1'],
            'tanggal_bayar' => ['required', 'date', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
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
        if (config('finance.v2')) {
            FlexibleFinanceController::client($request);
            $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:300']]);

            return Idempotency::run($request, $request->user(), 'dues-payments.reverse', $data + ['id' => $id], fn () => Api::data(Present::pembayaran(
                FlexibleDues::reverse($request->user(), $id, $data['alasan'])
            )));
        }
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'dues-payments.reverse', $data + ['id' => $id], fn () => Api::data(Present::pembayaran(
            Dues::reversePayment($request->user(), $id, $data['alasan'])
        )));
    }

    public function correctPayment(Request $request, string $id): JsonResponse
    {
        if (config('finance.v2')) {
            return app(FlexibleFinanceController::class)->correctPayment($request, $id);
        }
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:300'],
            'periode' => ['required', 'array', 'min:1', 'max:24'],
            'periode.*' => ['required', 'date_format:Y-m'],
            'nominal' => ['required', 'integer', 'min:1'],
            'tanggal_bayar' => ['required', 'date', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
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

        $reversed = self::reversedIds();
        $response = Api::paginate($request, $q, fn (TransaksiKas $k) => Present::kas($k, isset($reversed[$k->id])));
        $body = $response->getData(true);
        $body['meta']['saldo'] = Dues::balance();

        return $response->setData($body);
    }

    public function storeCash(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:pemasukan,pengeluaran,saldo_awal'],
            'nominal' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'tanggal' => ['required', 'date', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'kategori' => ['required', 'string', 'max:60'],
            'keterangan' => ['required', 'string', 'min:5', 'max:500'],
            'konfirmasi_saldo_negatif' => ['sometimes', 'boolean'],
        ]);

        return Idempotency::run($request, $request->user(), 'cash.store', $data, function () use ($request, $data) {
            $entry = Dues::recordCash($request->user(), $data['jenis'], (int) $data['nominal'], $data['tanggal'], $data['kategori'], $data['keterangan'],
                (bool) ($data['konfirmasi_saldo_negatif'] ?? false));
            $warning = Dues::balance() < 0 ? 'Saldo kas menjadi negatif setelah pencatatan ini.' : null;

            return Api::data(Present::kas($entry), 201, array_filter(['peringatan' => $warning]));
        });
    }

    public function reverseCash(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'cash.reverse', $data + ['id' => $id], fn () => Api::data(Present::kas(
            Dues::reverseCash($request->user(), $id, $data['alasan'])
        ), 201));
    }
}
