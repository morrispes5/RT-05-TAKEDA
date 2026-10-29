<?php

namespace App\Domain\Finance;

use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\PembayaranIuran;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** M18 domain. HTTP mutations must additionally use Idempotency::run. */
final class FlexibleDues
{
    public static function lock(): void
    {
        DB::select("SELECT pg_advisory_xact_lock(hashtext('rt05:keuangan'))");
    }

    private static function reason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 300) {
            throw new DomainConflict('REASON_REQUIRED', 'Alasan wajib diisi 5–300 karakter.', 422);
        }
    }

    private static function charges(string $house, array $periods)
    {
        $dates = array_values(array_unique(array_map(fn ($p) => Dues::period($p)->toDateString(), $periods)));
        if ($dates === []) {
            throw new DomainConflict('PERIODS_REQUIRED', 'Pilih minimal satu periode.', 422);
        }
        if (! Rumah::whereKey($house)->lockForUpdate()->exists()) {
            throw new DomainConflict('HOUSE_NOT_FOUND', 'Rumah tidak ditemukan.', 404);
        }
        TagihanIuran::where('rumah_id', $house)->whereIn('periode', $dates)->orderBy('periode')->lockForUpdate()->get();
        $rows = DB::table('rt05_tagihan_saldo')->where('rumah_id', $house)->whereIn('periode', $dates)->orderBy('periode')->get();
        if ($rows->count() !== count($dates)) {
            throw new DomainConflict('CHARGE_NOT_FOUND', 'Sebagian periode belum memiliki tagihan.', 422);
        }

        return $rows;
    }

    /** Fingerprint includes balances, not merely the chosen periods. */
    public static function preview(string $house, array $periods): array
    {
        return DB::transaction(function () use ($house, $periods) {
            self::lock();
            $rows = self::charges($house, $periods);
            $state = $rows->map(fn ($r) => [$r->id, (int) $r->nominal_efektif, (int) $r->terbayar_uang, (int) $r->terbayar_titipan, (int) $r->keringanan, (int) $r->sisa])->all();
            $lots = DB::table('rt05_titipan_saldo')->where('rumah_id', $house)->orderBy('id')->get(['id', 'saldo'])->all();

            return ['sisa' => (int) $rows->sum('sisa'), 'titipan' => (int) collect($lots)->sum('saldo'),
                'fingerprint' => hash('sha256', json_encode([$house, $state, $lots], JSON_THROW_ON_ERROR))];
        });
    }

    private static function checkPreview(string $house, array $periods, string $fingerprint): void
    {
        if (! hash_equals(self::preview($house, $periods)['fingerprint'], $fingerprint)) {
            throw new DomainConflict('FINANCE_PREVIEW_STALE', 'Data keuangan berubah. Periksa ulang rincian sebelum menyimpan.');
        }
    }

    public static function record(Akun $actor, string $house, array $periods, int $amount, string $date,
        ?string $shortfall, ?string $excess, ?string $reason, string $fingerprint, ?string $replaces = null): PembayaranIuran
    {
        if ($amount <= 0 || $amount > 1000000000) {
            throw new DomainConflict('INVALID_AMOUNT', 'Nominal penerimaan harus lebih dari nol dan paling besar Rp1 miliar.', 422);
        }

        return DB::transaction(function () use ($actor, $house, $periods, $amount, $date, $shortfall, $excess, $reason, $fingerprint, $replaces) {
            self::lock();
            self::checkPreview($house, $periods, $fingerprint);
            $charges = self::charges($house, $periods);
            $due = (int) $charges->sum('sisa');
            if ($amount < $due && ! in_array($shortfall, ['kurang_bayar', 'keringanan'], true)) {
                throw new DomainConflict('SHORTFALL_CHOICE_REQUIRED', 'Pilih kurang bayar atau lunas dengan keringanan.', 422);
            }
            if ($amount > $due && ! in_array($excess, ['sumbangan', 'titipan'], true)) {
                throw new DomainConflict('EXCESS_CHOICE_REQUIRED', 'Pilih sumbangan ke kas atau titipan.', 422);
            }
            if ($amount < $due && $shortfall === 'keringanan') {
                self::reason($reason ?? '');
            }
            $extra = max(0, $amount - $due);
            $seq = DB::selectOne("SELECT nextval('pembayaran_nomor_seq') AS n")->n;
            $payment = PembayaranIuran::create(['rumah_id' => $house, 'diterima_oleh_id' => $actor->id,
                'nomor_bukti' => sprintf('IUR-%s-%06d', now('Asia/Jakarta')->format('Y'), $seq),
                'tanggal_bayar' => $date, 'total_bayar' => $amount, 'status' => 'tercatat',
                'nominal_sumbangan' => $excess === 'sumbangan' ? $extra : 0,
                'nominal_titipan' => $excess === 'titipan' ? $extra : 0, 'versi_keuangan' => 2, 'menggantikan_id' => $replaces]);
            $left = $amount;
            foreach ($charges as $charge) {
                $allocated = min($left, (int) $charge->sisa);
                if ($allocated > 0) {
                    self::insert('alokasi_pembayaran', ['tagihan_id' => $charge->id, 'pembayaran_id' => $payment->id, 'nominal_alokasi' => $allocated]);
                }
                $left -= $allocated;
                if ($shortfall === 'keringanan' && $charge->sisa > $allocated) {
                    self::insert('keringanan_iuran', ['tagihan_id' => $charge->id, 'pembayaran_id' => $payment->id,
                        'nominal' => (int) $charge->sisa - $allocated, 'alasan' => $reason, 'dicatat_oleh_id' => $actor->id]);
                }
            }
            if ($extra > 0 && $excess === 'titipan') {
                $id = (string) Str::uuid();
                self::insert('mutasi_titipan', ['id' => $id, 'lot_id' => $id, 'rumah_id' => $house,
                    'pembayaran_id' => $payment->id, 'jenis' => 'masuk', 'nominal' => $extra, 'arah' => 1, 'dicatat_oleh_id' => $actor->id]);
            }
            self::sync($charges->pluck('id')->all());
            $cash = self::insert('transaksi_kas', ['jenis' => 'pemasukan', 'sumber' => 'iuran', 'kategori' => 'Penerimaan iuran',
                'nominal' => $amount, 'tanggal' => $date, 'keterangan' => 'Penerimaan '.$payment->nomor_bukti,
                'pembayaran_id' => $payment->id, 'dicatat_oleh_id' => $actor->id]);
            self::insert('riwayat_transaksi_kas', ['transaksi_kas_id' => $cash, 'diubah_oleh_id' => $actor->id,
                'aksi' => 'dicatat', 'sesudah' => json_encode(['nominal' => $amount]), 'alasan' => 'Penerimaan offline M18']);
            Audit::record($actor, 'pembayaran_iuran.dicatat', 'pembayaran_iuran', $payment->id, ['total' => $amount]);
            Outbox::record('iuran.pembayaran_dicatat', 'pembayaran_iuran', $payment->id, ['rumah_id' => $house, 'periode' => $periods]);

            return $payment;
        });
    }

    public static function useDeposit(Akun $actor, string $house, array $periods, int $amount, string $date, string $fingerprint, ?string $replaces = null): string
    {
        return DB::transaction(function () use ($actor, $house, $periods, $amount, $date, $fingerprint, $replaces) {
            self::lock();
            self::checkPreview($house, $periods, $fingerprint);
            $charges = self::charges($house, $periods);
            $lots = DB::table('rt05_titipan_saldo')->where('rumah_id', $house)->where('saldo', '>', 0)->orderBy('created_at')->orderBy('id')->get();
            if ($amount <= 0 || $amount > min((int) $charges->sum('sisa'), (int) $lots->sum('saldo'))) {
                throw new DomainConflict('INVALID_DEPOSIT_AMOUNT', 'Nominal melebihi titipan atau sisa tagihan.', 422);
            }
            $usage = self::insert('penggunaan_titipan', ['rumah_id' => $house, 'nominal' => $amount, 'tanggal' => $date, 'dicatat_oleh_id' => $actor->id, 'menggantikan_id' => $replaces]);
            $left = $amount;
            foreach ($lots as $lot) {
                $used = min($left, (int) $lot->saldo);
                if ($used > 0) {
                    self::insert('mutasi_titipan', ['rumah_id' => $house, 'pembayaran_id' => $lot->pembayaran_id, 'lot_id' => $lot->id,
                        'penggunaan_titipan_id' => $usage, 'jenis' => 'pakai', 'nominal' => $used, 'arah' => -1, 'dicatat_oleh_id' => $actor->id]);
                }
                $left -= $used;
            }
            $left = $amount;
            foreach ($charges as $charge) {
                $used = min($left, (int) $charge->sisa);
                if ($used > 0) {
                    self::insert('alokasi_pembayaran', ['tagihan_id' => $charge->id, 'penggunaan_titipan_id' => $usage, 'nominal_alokasi' => $used]);
                }
                $left -= $used;
            }
            self::sync($charges->pluck('id')->all());
            Audit::record($actor, 'titipan.dipakai', 'penggunaan_titipan', $usage, ['nominal' => $amount]);

            return $usage;
        });
    }

    public static function reverseDeposit(Akun $actor, string $id, string $reason): void
    {
        self::reason($reason);
        DB::transaction(function () use ($actor, $id, $reason) {
            self::lock();
            $usage = DB::table('penggunaan_titipan')->where('id', $id)->lockForUpdate()->first();
            if (! $usage || $usage->dibatalkan_at !== null) {
                throw new DomainConflict('DEPOSIT_NOT_REVERSIBLE', 'Pemakaian titipan tidak ditemukan atau sudah dibatalkan.');
            }
            $charges = DB::table('alokasi_pembayaran')->where('penggunaan_titipan_id', $id)->pluck('tagihan_id')->all();
            DB::table('alokasi_pembayaran')->where('penggunaan_titipan_id', $id)->whereNull('dibatalkan_at')->update(['dibatalkan_at' => now()]);
            foreach (DB::table('mutasi_titipan')->where('penggunaan_titipan_id', $id)->where('jenis', 'pakai')->get() as $entry) {
                self::reverseLot($actor, $entry);
            }
            DB::table('penggunaan_titipan')->where('id', $id)->update(self::cancellation($actor, $reason));
            self::sync($charges);
            Audit::record($actor, 'titipan.dibalik', 'penggunaan_titipan', $id, ['alasan' => $reason]);
        });
    }

    public static function reverse(Akun $actor, string $id, string $reason): PembayaranIuran
    {
        self::reason($reason);

        return DB::transaction(function () use ($actor, $id, $reason) {
            self::lock();
            $payment = PembayaranIuran::whereKey($id)->lockForUpdate()->first();
            if (! $payment || $payment->status !== 'tercatat') {
                throw new DomainConflict('PAYMENT_NOT_REVERSIBLE', 'Pembayaran tidak ditemukan atau sudah dibatalkan.');
            }
            $lot = DB::table('rt05_titipan_saldo')->where('pembayaran_id', $id)->first();
            if ($lot && (int) $lot->saldo !== (int) $payment->nominal_titipan) {
                throw new DomainConflict('DEPOSIT_ALREADY_USED', 'Balik pemakaian titipan terkait terlebih dahulu.');
            }
            if ($lot) {
                self::reverseLot($actor, DB::table('mutasi_titipan')->where('id', $lot->id)->first());
            }
            $charges = DB::table('alokasi_pembayaran')->where('pembayaran_id', $id)->pluck('tagihan_id')
                ->merge(DB::table('keringanan_iuran')->where('pembayaran_id', $id)->pluck('tagihan_id'))->unique()->all();
            DB::table('keringanan_iuran')->where('pembayaran_id', $id)->whereNull('dibatalkan_at')->update(self::cancellation($actor, $reason));
            $result = Dues::reversePayment($actor, $id, $reason);
            self::sync($charges);

            return $result;
        });
    }

    public static function setHouseRate(Akun $actor, string $house, string $period, ?int $amount, string $reason, ?string $replaces = null): string
    {
        self::reason($reason);
        $date = Dues::period($period)->toDateString();
        if ($date <= now('Asia/Jakarta')->startOfMonth()->toDateString() || ($amount !== null && ($amount < 1000 || $amount > 10000000))) {
            throw new DomainConflict('INVALID_RATE', 'Tarif berlaku mulai bulan berikutnya; nominal khusus Rp1.000–Rp10 juta.', 422);
        }

        return DB::transaction(function () use ($actor, $house, $date, $amount, $reason, $replaces) {
            self::lock();
            if (! Rumah::whereKey($house)->lockForUpdate()->exists()) {
                throw new DomainConflict('HOUSE_NOT_FOUND', 'Rumah tidak ditemukan.', 404);
            }
            if (TagihanIuran::where('rumah_id', $house)->where('periode', '>=', $date)->exists()) {
                throw new DomainConflict('RATE_ALREADY_BILLED', 'Periode sudah memiliki tagihan. Gunakan koreksi tagihan dengan alasan.');
            }
            if ($replaces !== null) {
                $old = DB::table('tarif_rumah')->where('id', $replaces)->where('rumah_id', $house)->whereNull('dibatalkan_at')->first();
                if (! $old || $old->mulai_periode !== $date) {
                    throw new DomainConflict('RATE_NOT_REPLACEABLE', 'Tarif pengganti harus untuk rumah dan bulan yang sama.');
                }
                DB::table('tarif_rumah')->where('id', $replaces)->update(self::cancellation($actor, $reason));
            }
            $id = self::insert('tarif_rumah', ['rumah_id' => $house, 'mode' => $amount === null ? 'umum' : 'khusus',
                'nominal' => $amount, 'mulai_periode' => $date, 'alasan' => $reason, 'dibuat_oleh_id' => $actor->id, 'menggantikan_id' => $replaces]);
            Audit::record($actor, 'tarif_rumah.dicatat', 'tarif_rumah', $id, ['alasan' => $reason]);

            return $id;
        });
    }

    public static function adjustCharge(Akun $actor, string $id, int $delta, string $reason): string
    {
        self::reason($reason);

        return DB::transaction(function () use ($actor, $id, $delta, $reason) {
            self::lock();
            $charge = DB::table('rt05_tagihan_saldo')->where('id', $id)->first();
            if (! $charge || $delta === 0 || abs($delta) > 10000000 || (int) $charge->sisa + $delta < 0) {
                throw new DomainConflict('INVALID_CHARGE_ADJUSTMENT', 'Koreksi tidak boleh mengurangi tagihan di bawah pembayaran dan keringanan.', 422);
            }
            $adjustment = self::insert('penyesuaian_tagihan', ['tagihan_id' => $id, 'delta' => $delta, 'alasan' => $reason, 'dicatat_oleh_id' => $actor->id]);
            self::sync([$id]);
            Audit::record($actor, 'tagihan_iuran.disesuaikan', 'penyesuaian_tagihan', $adjustment, ['delta' => $delta, 'alasan' => $reason]);

            return $adjustment;
        });
    }

    public static function correct(Akun $actor, string $id, string $reason, array $periods, int $amount, string $date, ?string $shortfall, ?string $excess, string $fingerprint): PembayaranIuran
    {
        return DB::transaction(function () use ($actor, $id, $reason, $periods, $amount, $date, $shortfall, $excess, $fingerprint) {
            self::lock();
            $old = PembayaranIuran::findOrFail($id);
            self::checkPreview($old->rumah_id, $periods, $fingerprint);
            self::reverse($actor, $id, $reason);

            return self::record($actor, $old->rumah_id, $periods, $amount, $date, $shortfall, $excess, $reason,
                self::preview($old->rumah_id, $periods)['fingerprint'], $id);
        });
    }

    public static function relieve(Akun $actor, string $chargeId, int $amount, string $reason, ?string $replaces = null): string
    {
        self::reason($reason);

        return DB::transaction(function () use ($actor, $chargeId, $amount, $reason, $replaces) {
            self::lock();
            $charge = DB::table('rt05_tagihan_saldo')->where('id', $chargeId)->first();
            if (! $charge || $amount <= 0 || $amount > (int) $charge->sisa) {
                throw new DomainConflict('INVALID_RELIEF', 'Keringanan harus lebih dari nol dan tidak melebihi sisa tagihan.', 422);
            }
            $id = self::insert('keringanan_iuran', ['tagihan_id' => $chargeId, 'nominal' => $amount,
                'alasan' => $reason, 'dicatat_oleh_id' => $actor->id, 'menggantikan_id' => $replaces]);
            self::sync([$chargeId]);
            Audit::record($actor, 'keringanan.dicatat', 'keringanan_iuran', $id, ['nominal' => $amount, 'alasan' => $reason]);

            return $id;
        });
    }

    public static function reverseRelief(Akun $actor, string $id, string $reason): void
    {
        self::reason($reason);
        DB::transaction(function () use ($actor, $id, $reason) {
            self::lock();
            $relief = DB::table('keringanan_iuran')->where('id', $id)->whereNull('dibatalkan_at')->first();
            if (! $relief || $relief->pembayaran_id !== null) {
                throw new DomainConflict('RELIEF_NOT_REVERSIBLE', 'Keringanan terkait pembayaran dibalik melalui koreksi pembayaran.', 422);
            }
            DB::table('keringanan_iuran')->where('id', $id)->update(self::cancellation($actor, $reason));
            self::sync([$relief->tagihan_id]);
            Audit::record($actor, 'keringanan.dibalik', 'keringanan_iuran', $id, ['alasan' => $reason]);
        });
    }

    public static function reverseAdjustment(Akun $actor, string $id, string $reason): string
    {
        self::reason($reason);

        return DB::transaction(function () use ($actor, $id, $reason) {
            self::lock();
            $original = DB::table('penyesuaian_tagihan')->where('id', $id)->first();
            if (! $original || $original->membalik_id !== null || DB::table('penyesuaian_tagihan')->where('membalik_id', $id)->exists()) {
                throw new DomainConflict('ADJUSTMENT_NOT_REVERSIBLE', 'Koreksi tagihan tidak dapat dibalik lagi.');
            }
            $charge = DB::table('rt05_tagihan_saldo')->where('id', $original->tagihan_id)->first();
            if ((int) $charge->sisa - (int) $original->delta < 0) {
                throw new DomainConflict('ADJUSTMENT_ALREADY_SETTLED', 'Balik pelunasan terkait sebelum membalik koreksi tagihan.');
            }
            $newId = self::insert('penyesuaian_tagihan', ['tagihan_id' => $original->tagihan_id, 'delta' => -(int) $original->delta,
                'alasan' => $reason, 'dicatat_oleh_id' => $actor->id, 'membalik_id' => $id]);
            self::sync([$original->tagihan_id]);
            Audit::record($actor, 'tagihan_iuran.koreksi_dibalik', 'penyesuaian_tagihan', $newId, ['alasan' => $reason]);

            return $newId;
        });
    }

    private static function reverseLot(Akun $actor, object $entry): void
    {
        self::insert('mutasi_titipan', ['rumah_id' => $entry->rumah_id, 'pembayaran_id' => $entry->pembayaran_id,
            'lot_id' => $entry->lot_id, 'penggunaan_titipan_id' => $entry->penggunaan_titipan_id,
            'jenis' => 'pembalikan', 'nominal' => $entry->nominal, 'arah' => -$entry->arah,
            'membalik_id' => $entry->id, 'dicatat_oleh_id' => $actor->id]);
    }

    private static function cancellation(Akun $actor, string $reason): array
    {
        return ['dibatalkan_at' => now(), 'dibatalkan_oleh_id' => $actor->id, 'alasan_pembatalan' => $reason];
    }

    /** Status tagihan mengikuti sisa (lunas ⇔ sisa 0); dipakai juga impor buku lama. */
    public static function sync(array $ids): void
    {
        foreach (DB::table('rt05_tagihan_saldo')->whereIn('id', $ids)->get() as $charge) {
            DB::table('tagihan_iuran')->where('id', $charge->id)->update(['status' => (int) $charge->sisa === 0 ? 'lunas' : 'belum_bayar']);
        }
    }

    private static function insert(string $table, array $data): string
    {
        $data['id'] ??= (string) Str::uuid();
        DB::table($table)->insert($data);

        return $data['id'];
    }
}
