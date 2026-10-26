<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Dues;
use App\Domain\Finance\DuesConfirmations;
use App\Domain\Finance\FlexibleDues;
use App\Exceptions\DomainConflict;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\TagihanIuran;
use App\Models\TarifIuran;
use App\Support\Audit;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlexibleFinanceController extends Controller
{
    public static function enabled(): void
    {
        abort_unless(config('finance.v2'), 404);
    }

    public function recap(Request $request): JsonResponse
    {
        self::enabled();
        $period = Dues::period((string) $request->query('periode', now('Asia/Jakarta')->format('Y-m')));

        return DB::transaction(function () use ($period) {
            FlexibleDues::lock();
            $arrears = DB::table('rt05_tagihan_saldo')->where('periode', '<', $period->toDateString())->where('sisa', '>', 0)
                ->selectRaw('rumah_id, count(*) AS bulan, sum(sisa) AS nominal')->groupBy('rumah_id')->get()->keyBy('rumah_id');
            $rows = TagihanIuran::with(['rumah', 'alokasi.pembayaran'])->where('periode', $period->toDateString())->get()
                ->sortBy(fn ($t) => $t->rumah->kode_rumah)->map(fn ($t) => Present::tagihan($t) + [
                    'rumah_id' => $t->rumah_id, 'kode_rumah' => $t->rumah->kode_rumah,
                    'bulan_tunggakan_sebelumnya' => (int) ($arrears[$t->rumah_id]->bulan ?? 0),
                    'tanggal_bayar' => $t->alokasi->whereNull('dibatalkan_at')->first()?->pembayaran?->tanggal_bayar?->toDateString(),
                ])->values();

            return Api::data(['periode' => $period->format('Y-m'), 'tarif' => Dues::rateFor($period)->nominal, 'jumlah_rumah' => $rows->count(),
                'lunas' => ['rumah' => $rows->where('status', 'lunas')->count(), 'nominal' => (int) $rows->where('status', 'lunas')->sum('nominal_efektif')],
                'belum_bayar' => ['rumah' => $rows->where('status', 'belum_bayar')->count(), 'nominal' => (int) $rows->sum('sisa')],
                'terbayar' => (int) $rows->sum('terbayar'), 'keringanan' => (int) $rows->sum('keringanan'), 'sisa' => (int) $rows->sum('sisa'),
                'tunggakan_sebelumnya' => ['rumah' => $arrears->count(), 'nominal' => (int) $arrears->sum('nominal')],
                'rumah' => $rows, 'catatan' => 'Posisi pelunasan saat ini; kas dihitung dari penerimaan uang, bukan nominal tagihan.']);
        });
    }

    public static function client(Request $request): void
    {
        self::enabled();
        if ((int) $request->input('versi_keuangan') !== 2) {
            throw new DomainConflict('FINANCE_CLIENT_UPDATE_REQUIRED', 'Perbarui aplikasi untuk mencatat keuangan.', 422);
        }
    }

    public static function paymentData(Request $request, bool $house = true): array
    {
        self::client($request);

        return $request->validate([
            ...($house ? ['rumah_id' => ['required', 'uuid']] : []),
            'periode' => ['required', 'array', 'min:1', 'max:24'], 'periode.*' => ['required', 'date_format:Y-m', 'distinct'],
            'nominal' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'tanggal_bayar' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'perlakuan_kurang' => ['nullable', 'in:kurang_bayar,keringanan'],
            'perlakuan_lebih' => ['nullable', 'in:sumbangan,titipan'],
            'alasan' => ['nullable', 'string', 'min:5', 'max:300'],
            'fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'versi_keuangan' => ['required', 'integer', 'in:2'],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        self::client($request);
        $data = $request->validate(['rumah_id' => ['required', 'uuid'], 'periode' => ['required', 'array', 'min:1', 'max:24'],
            'periode.*' => ['required', 'date_format:Y-m', 'distinct'], 'nominal' => ['required', 'integer', 'min:1', 'max:1000000000']]);

        return DB::transaction(function () use ($data) {
            FlexibleDues::lock();
            $preview = FlexibleDues::preview($data['rumah_id'], $data['periode']);
            $rows = DB::table('rt05_tagihan_saldo')->where('rumah_id', $data['rumah_id'])
                ->whereIn('periode', array_map(fn ($p) => $p.'-01', $data['periode']))->orderBy('periode')->get();
            $left = (int) $data['nominal'];
            $allocation = [];
            foreach ($rows as $row) {
                $paid = min($left, (int) $row->sisa);
                $allocation[] = ['tagihan_id' => $row->id, 'periode' => substr($row->periode, 0, 7), 'sisa_sebelum' => (int) $row->sisa,
                    'alokasi' => $paid, 'sisa_sesudah' => (int) $row->sisa - $paid];
                $left -= $paid;
            }

            return Api::data($preview + ['kas_diterima' => (int) $data['nominal'], 'kurang' => max(0, $preview['sisa'] - $data['nominal']),
                'lebih' => max(0, $data['nominal'] - $preview['sisa']), 'alokasi' => $allocation]);
        });
    }

    public function storePayment(Request $request): JsonResponse
    {
        $d = self::paymentData($request);

        return Idempotency::run($request, $request->user(), 'dues-payments.store', $d, fn () => Api::data(Present::pembayaran(
            FlexibleDues::record($request->user(), $d['rumah_id'], $d['periode'], (int) $d['nominal'], $d['tanggal_bayar'],
                $d['perlakuan_kurang'] ?? null, $d['perlakuan_lebih'] ?? null, $d['alasan'] ?? null, $d['fingerprint'])
        ), 201));
    }

    public function correctPayment(Request $request, string $id): JsonResponse
    {
        $d = self::paymentData($request, false);
        $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'dues-payments.correct', $d + ['id' => $id], fn () => Api::data(Present::pembayaran(
            FlexibleDues::correct($request->user(), $id, $d['alasan'], $d['periode'], (int) $d['nominal'], $d['tanggal_bayar'],
                $d['perlakuan_kurang'] ?? null, $d['perlakuan_lebih'] ?? null, $d['fingerprint'])
        ), 201));
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $d = self::paymentData($request, false);

        return Idempotency::run($request, $request->user(), 'dues-confirmations.approve', $d + ['id' => $id], function () use ($request, $id, $d) {
            $confirmation = DuesConfirmations::approve($request->user(), $id, $d);

            return Api::data(['id' => $confirmation->id, 'status' => $confirmation->status, 'pembayaran_id' => $confirmation->pembayaran_id], 201);
        });
    }

    public function rates(Request $request, string $id): JsonResponse
    {
        self::enabled();

        return Api::data(DB::table('tarif_rumah')->where('rumah_id', $id)->orderByDesc('mulai_periode')->get());
    }

    public function storeRate(Request $request, string $id, ?string $rateId = null): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['mulai_periode' => ['required', 'date_format:Y-m'], 'nominal' => ['present', 'nullable', 'integer', 'min:1000', 'max:10000000'],
            'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'house-rates.store', $d + ['id' => $id, 'replaces' => $rateId], fn () => Api::data([
            'id' => FlexibleDues::setHouseRate($request->user(), $id, $d['mulai_periode'], $d['nominal'], $d['alasan'], $rateId),
        ], 201));
    }

    public function deposits(string $id): JsonResponse
    {
        self::enabled();

        return Api::data(['saldo' => (int) DB::table('rt05_titipan_saldo')->where('rumah_id', $id)->sum('saldo'),
            'lot' => DB::table('rt05_titipan_saldo')->where('rumah_id', $id)->orderBy('created_at')->get(),
            'riwayat' => DB::table('mutasi_titipan')->where('rumah_id', $id)->orderBy('created_at')->get()]);
    }

    public function useDeposit(Request $request): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['rumah_id' => ['required', 'uuid'], 'periode' => ['required', 'array', 'min:1', 'max:24'],
            'periode.*' => ['required', 'date_format:Y-m', 'distinct'], 'nominal' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);

        return Idempotency::run($request, $request->user(), 'deposit-usages.store', $d, fn () => Api::data([
            'id' => FlexibleDues::useDeposit($request->user(), $d['rumah_id'], $d['periode'], $d['nominal'], $d['tanggal'], $d['fingerprint']),
        ], 201));
    }

    public function reverseDeposit(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'deposit-usages.reverse', $d + ['id' => $id], function () use ($request, $id, $d) {
            FlexibleDues::reverseDeposit($request->user(), $id, $d['alasan']);

            return Api::data(['id' => $id, 'dibatalkan' => true]);
        });
    }

    public function relief(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['nominal' => ['required', 'integer', 'min:1', 'max:1000000000'], 'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'reliefs.store', $d + ['id' => $id], fn () => Api::data([
            'id' => FlexibleDues::relieve($request->user(), $id, $d['nominal'], $d['alasan']),
        ], 201));
    }

    public function adjustment(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['delta' => ['required', 'integer', 'not_in:0', 'min:-10000000', 'max:10000000'], 'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'adjustments.store', $d + ['id' => $id], fn () => Api::data([
            'id' => FlexibleDues::adjustCharge($request->user(), $id, $d['delta'], $d['alasan']),
        ], 201));
    }

    public function reverseRelief(Request $request, string $id): JsonResponse
    {
        return $this->reverseEntry($request, $id, 'relief');
    }

    public function reverseAdjustment(Request $request, string $id): JsonResponse
    {
        return $this->reverseEntry($request, $id, 'adjustment');
    }

    private function reverseEntry(Request $request, string $id, string $type): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), $type.'.reverse', $d + ['id' => $id], function () use ($request, $id, $type, $d) {
            if ($type === 'relief') {
                FlexibleDues::reverseRelief($request->user(), $id, $d['alasan']);
            } else {
                FlexibleDues::reverseAdjustment($request->user(), $id, $d['alasan']);
            }

            return Api::data(['id' => $id, 'dibatalkan' => true]);
        });
    }

    public function correctRate(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['nominal' => ['required', 'integer', 'min:1000', 'max:10000000'], 'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'dues-rates.correct', $d + ['id' => $id], function () use ($request, $id, $d) {
            FlexibleDues::lock();
            $old = TarifIuran::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($old->dibatalkan_at !== null || TagihanIuran::where('periode', '>=', $old->mulai_periode)->exists()) {
                throw new DomainConflict('RATE_ALREADY_BILLED', 'Tarif yang sudah ditagihkan tidak boleh dikoreksi.');
            }
            $old->forceFill(['dibatalkan_at' => now(), 'dibatalkan_oleh_id' => $request->user()->id, 'alasan_pembatalan' => $d['alasan']])->save();
            $new = TarifIuran::create(['nominal' => $d['nominal'], 'mulai_periode' => $old->mulai_periode,
                'alasan' => $d['alasan'], 'dibuat_oleh_id' => $request->user()->id, 'menggantikan_id' => $id]);
            Audit::record($request->user(), 'tarif_iuran.dikoreksi', 'tarif_iuran', $new->id, ['menggantikan_id' => $id]);

            return Api::data(['id' => $new->id, 'menggantikan_id' => $id], 201);
        });
    }

    public function storeCommonRate(Request $request): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['mulai_periode' => ['required', 'date_format:Y-m'], 'nominal' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'dues-rates.store', $d, function () use ($request, $d) {
            FlexibleDues::lock();
            $date = Dues::period($d['mulai_periode'])->toDateString();
            if ($date <= now('Asia/Jakarta')->startOfMonth()->toDateString() || TagihanIuran::where('periode', '>=', $date)->exists()) {
                throw new DomainConflict('RATE_ALREADY_BILLED', 'Pilih bulan mendatang yang belum ditagihkan.', 422);
            }
            if (TarifIuran::whereNull('dibatalkan_at')->where('mulai_periode', $date)->exists()) {
                throw new DomainConflict('RATE_PERIOD_EXISTS', 'Tarif bulan tersebut sudah ada. Gunakan koreksi tarif.');
            }
            $new = TarifIuran::create(['mulai_periode' => $date, 'nominal' => $d['nominal'], 'alasan' => $d['alasan'], 'dibuat_oleh_id' => $request->user()->id]);
            Audit::record($request->user(), 'tarif_iuran.dibuat', 'tarif_iuran', $new->id, $d);

            return Api::data(['id' => $new->id, 'nominal' => $new->nominal, 'mulai_periode' => $d['mulai_periode'], 'alasan' => $d['alasan'],
                'created_at' => Present::ts($new->created_at)], 201);
        });
    }

    public function correctCash(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:300'],
            'jenis' => ['required', 'in:pemasukan,pengeluaran'], 'nominal' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'kategori' => ['required', 'string', 'max:60'], 'keterangan' => ['required', 'string', 'min:5', 'max:500'],
            'konfirmasi_saldo_negatif' => ['sometimes', 'boolean']]);

        return Idempotency::run($request, $request->user(), 'cash.correct', $d + ['id' => $id], function () use ($request, $id, $d) {
            FlexibleDues::lock();
            Dues::reverseCash($request->user(), $id, $d['alasan']);
            $replacement = Dues::recordCash($request->user(), $d['jenis'], $d['nominal'], $d['tanggal'], $d['kategori'],
                $d['keterangan'], (bool) ($d['konfirmasi_saldo_negatif'] ?? false));
            Audit::record($request->user(), 'transaksi_kas.dikoreksi', 'transaksi_kas', $replacement->id, ['menggantikan_id' => $id, 'alasan' => $d['alasan']]);

            return Api::data(Present::kas($replacement) + ['menggantikan_id' => $id], 201);
        });
    }

    public function correctRelief(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['nominal' => ['required', 'integer', 'min:1', 'max:1000000000'], 'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'reliefs.correct', $d + ['id' => $id], function () use ($request, $id, $d) {
            FlexibleDues::lock();
            $old = DB::table('keringanan_iuran')->where('id', $id)->first();
            abort_unless($old, 404);
            FlexibleDues::reverseRelief($request->user(), $id, $d['alasan']);
            $new = FlexibleDues::relieve($request->user(), $old->tagihan_id, $d['nominal'], $d['alasan'], $id);

            return Api::data(['id' => $new, 'menggantikan_id' => $id], 201);
        });
    }

    public function correctAdjustment(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['delta' => ['required', 'integer', 'not_in:0', 'min:-10000000', 'max:10000000'], 'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'adjustments.correct', $d + ['id' => $id], function () use ($request, $id, $d) {
            FlexibleDues::lock();
            $old = DB::table('penyesuaian_tagihan')->where('id', $id)->first();
            abort_unless($old, 404);
            FlexibleDues::reverseAdjustment($request->user(), $id, $d['alasan']);
            $new = FlexibleDues::adjustCharge($request->user(), $old->tagihan_id, $d['delta'], $d['alasan']);
            Audit::record($request->user(), 'tagihan_iuran.koreksi_diganti', 'penyesuaian_tagihan', $new, ['menggantikan_id' => $id]);

            return Api::data(['id' => $new, 'menggantikan_id' => $id], 201);
        });
    }

    public function correctDeposit(Request $request, string $id): JsonResponse
    {
        self::client($request);
        $d = $request->validate(['periode' => ['required', 'array', 'min:1', 'max:24'], 'periode.*' => ['required', 'date_format:Y-m', 'distinct'],
            'nominal' => ['required', 'integer', 'min:1', 'max:1000000000'], 'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'alasan' => ['required', 'string', 'min:5', 'max:300']]);

        return Idempotency::run($request, $request->user(), 'deposit-usages.correct', $d + ['id' => $id], function () use ($request, $id, $d) {
            FlexibleDues::lock();
            $old = DB::table('penggunaan_titipan')->where('id', $id)->first();
            abort_unless($old, 404);
            $preview = FlexibleDues::preview($old->rumah_id, $d['periode']);
            if (! hash_equals($preview['fingerprint'], $d['fingerprint'])) {
                throw new DomainConflict('FINANCE_PREVIEW_STALE', 'Data berubah. Periksa kembali sebelum menyimpan.');
            }
            FlexibleDues::reverseDeposit($request->user(), $id, $d['alasan']);
            $new = FlexibleDues::useDeposit($request->user(), $old->rumah_id, $d['periode'], $d['nominal'], $d['tanggal'],
                FlexibleDues::preview($old->rumah_id, $d['periode'])['fingerprint'], $id);

            return Api::data(['id' => $new, 'menggantikan_id' => $id], 201);
        });
    }
}
