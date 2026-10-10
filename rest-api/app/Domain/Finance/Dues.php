<?php

namespace App\Domain\Finance;

use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\AlokasiPembayaran;
use App\Models\PembayaranIuran;
use App\Models\RiwayatTransaksiKas;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Models\TarifIuran;
use App\Models\TransaksiKas;
use App\Support\Audit;
use App\Support\Outbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Iuran Rp75.000/rumah/bulan (BR10–BR17). Pembayaran OFFLINE dicatat pengurus; tanpa gateway.
 * - Tagihan per rumah/periode unik; nominal = snapshot tarif efektif (tarif baru tidak mengubah lama).
 * - Pembayaran melunasi penuh satu atau beberapa tagihan rumah yang sama; parsial/lebih ditolak.
 * - payment + alokasi + kas + audit + outbox dalam satu transaksi dengan row lock.
 * - Koreksi = pembalikan (alokasi nonaktif, tagihan dibuka, kas -asal) + pembayaran pengganti.
 */
final class Dues
{
    public static function period(string $yyyyMm): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yyyyMm)) {
            throw new DomainConflict('INVALID_PERIOD', 'Periode harus berformat YYYY-MM.', 422);
        }

        return CarbonImmutable::createFromFormat('!Y-m', $yyyyMm, 'UTC')->startOfMonth();
    }

    public static function rateFor(CarbonImmutable $period): TarifIuran
    {
        $rate = TarifIuran::where('mulai_periode', '<=', $period->toDateString())->orderByDesc('mulai_periode')->first();
        if (! $rate) {
            throw new DomainConflict('RATE_NOT_FOUND', 'Tarif iuran untuk periode ini belum ditetapkan.', 422);
        }

        return $rate;
    }

    /**
     * Buat tagihan untuk periode (idempotent, aman dijalankan ulang/bersamaan).
     *
     * @return int jumlah tagihan baru
     */
    public static function generate(CarbonImmutable $period, ?Akun $actor = null): int
    {
        $rate = self::rateFor($period);
        $due = $period->day(10)->toDateString();

        $houses = Rumah::whereNull('archived_at')
            ->where('tagihan_aktif', true)
            ->whereNotNull('mulai_tagih')
            ->where('mulai_tagih', '<=', $period->toDateString())
            ->pluck('id');

        $created = 0;
        foreach ($houses->chunk(200) as $chunk) {
            $rows = $chunk->map(fn ($id) => [
                'id' => (string) Str::uuid(),
                'tarif_id' => $rate->id,
                'rumah_id' => $id,
                'periode' => $period->toDateString(),
                'nominal_tagihan' => $rate->nominal,
                'jatuh_tempo' => $due,
                'status' => 'belum_bayar',
            ])->all();
            // ON CONFLICT DO NOTHING: tagihan lama (dan snapshot nominalnya) tidak pernah ditimpa.
            $created += DB::table('tagihan_iuran')->insertOrIgnore($rows);
        }

        if ($created > 0) {
            Audit::record($actor, 'tagihan_iuran.dibuat', 'tagihan_iuran', null, ['periode' => $period->format('Y-m'), 'jumlah' => $created]);
            Outbox::record('iuran.tagihan_dibuat', 'periode', null, ['periode' => $period->format('Y-m')]);
        }

        return $created;
    }

    /** Catch-up dari tarif pertama sampai bulan berjalan (aman setelah downtime). */
    public static function catchUp(?Akun $actor = null): int
    {
        $first = TarifIuran::min('mulai_periode');
        if (! $first) {
            return 0;
        }
        $cursor = CarbonImmutable::parse($first, 'UTC')->startOfMonth();
        // Bulan berjalan menurut kalender Asia/Jakarta, sebagai tanggal (tanpa konversi zona waktu).
        $now = self::period(CarbonImmutable::now('Asia/Jakarta')->format('Y-m'));
        $total = 0;
        while ($cursor <= $now) {
            $total += self::generate($cursor, $actor);
            $cursor = $cursor->addMonth();
        }

        return $total;
    }

    /**
     * Catat pembayaran offline untuk beberapa periode satu rumah.
     *
     * @param  list<string>  $periods  YYYY-MM
     */
    public static function recordPayment(Akun $pengurus, string $rumahId, array $periods, int $amount, string $paidOn, ?string $note, ?string $replacesId = null): PembayaranIuran
    {
        $periods = array_values(array_unique($periods));
        sort($periods);
        if ($periods === []) {
            throw new DomainConflict('PERIODS_REQUIRED', 'Pilih minimal satu periode.', 422);
        }
        $dates = array_map(fn ($p) => self::period($p)->toDateString(), $periods);

        return DB::transaction(function () use ($pengurus, $rumahId, $dates, $periods, $amount, $paidOn, $note, $replacesId) {
            // Urutan lock stabil: rumah lalu tagihan berdasarkan periode.
            $rumah = Rumah::whereKey($rumahId)->lockForUpdate()->first();
            if (! $rumah) {
                throw new DomainConflict('HOUSE_NOT_FOUND', 'Rumah tidak ditemukan.', 404);
            }
            $charges = TagihanIuran::where('rumah_id', $rumah->id)
                ->whereIn('periode', $dates)
                ->orderBy('periode')
                ->lockForUpdate()
                ->get();

            if ($charges->count() !== count($dates)) {
                throw new DomainConflict('CHARGE_NOT_FOUND', 'Sebagian periode belum memiliki tagihan untuk rumah ini.', 422);
            }
            if ($charges->contains(fn ($c) => $c->status !== 'belum_bayar')) {
                throw new DomainConflict('CHARGE_ALREADY_PAID', 'Sebagian periode sudah lunas. Muat ulang data lalu pilih periode yang belum lunas.');
            }

            $expected = (int) $charges->sum('nominal_tagihan');
            if ($amount !== $expected) {
                throw new DomainConflict('AMOUNT_MISMATCH', 'Nominal harus sama dengan total tagihan terpilih: Rp'.number_format($expected, 0, ',', '.').'. Pembayaran sebagian atau lebih belum didukung.', 422);
            }

            $payment = PembayaranIuran::create([
                'rumah_id' => $rumah->id,
                'diterima_oleh_id' => $pengurus->id,
                'nomor_bukti' => self::receiptNumber(),
                'tanggal_bayar' => $paidOn,
                'total_bayar' => $expected,
                'status' => 'tercatat',
                'catatan' => $note,
                'menggantikan_id' => $replacesId,
            ]);
            foreach ($charges as $charge) {
                AlokasiPembayaran::create([
                    'tagihan_id' => $charge->id,
                    'pembayaran_id' => $payment->id,
                    'nominal_alokasi' => $charge->nominal_tagihan,
                ]);
            }
            TagihanIuran::whereIn('id', $charges->pluck('id'))->update(['status' => 'lunas']);

            $cash = TransaksiKas::create([
                'jenis' => 'pemasukan',
                'sumber' => 'iuran',
                'kategori' => 'Iuran warga',
                'nominal' => $expected,
                'tanggal' => $paidOn,
                'keterangan' => 'Iuran '.$rumah->kode_rumah.' '.implode(', ', $periods).' ('.$payment->nomor_bukti.')',
                'pembayaran_id' => $payment->id,
                'dicatat_oleh_id' => $pengurus->id,
            ]);
            RiwayatTransaksiKas::create([
                'transaksi_kas_id' => $cash->id,
                'diubah_oleh_id' => $pengurus->id,
                'aksi' => 'dicatat',
                'sesudah' => ['nominal' => $expected, 'pembayaran_id' => $payment->id],
                'alasan' => 'Pencatatan pembayaran iuran offline',
            ]);

            Audit::record($pengurus, 'pembayaran_iuran.dicatat', 'pembayaran_iuran', $payment->id, [
                'rumah_id' => $rumah->id, 'periode' => $periods, 'total' => $expected,
            ]);
            Outbox::record('iuran.pembayaran_dicatat', 'pembayaran_iuran', $payment->id, ['rumah_id' => $rumah->id, 'periode' => $periods]);

            return $payment->load('alokasi.tagihan', 'rumah');
        });
    }

    /** Pembalikan penuh satu kali; tagihan dibuka kembali; ledger kas mendapat baris -asal. */
    public static function reversePayment(Akun $pengurus, string $paymentId, string $reason): PembayaranIuran
    {
        return DB::transaction(function () use ($pengurus, $paymentId, $reason) {
            $payment = PembayaranIuran::whereKey($paymentId)->lockForUpdate()->first();
            if (! $payment) {
                throw new DomainConflict('PAYMENT_NOT_FOUND', 'Pembayaran tidak ditemukan.', 404);
            }
            Rumah::whereKey($payment->rumah_id)->lockForUpdate()->first();
            if ($payment->status !== 'tercatat') {
                throw new DomainConflict('PAYMENT_ALREADY_REVERSED', 'Pembayaran ini sudah dibatalkan.');
            }

            $allocations = AlokasiPembayaran::where('pembayaran_id', $payment->id)->whereNull('dibatalkan_at')->lockForUpdate()->get();
            TagihanIuran::whereIn('id', $allocations->pluck('tagihan_id'))->lockForUpdate()->get();
            AlokasiPembayaran::whereIn('id', $allocations->pluck('id'))->update(['dibatalkan_at' => now()]);
            TagihanIuran::whereIn('id', $allocations->pluck('tagihan_id'))->update(['status' => 'belum_bayar']);

            $original = TransaksiKas::where('pembayaran_id', $payment->id)->firstOrFail();
            $reversal = TransaksiKas::create([
                'jenis' => 'pembalikan',
                'sumber' => 'iuran',
                'kategori' => $original->kategori,
                'nominal' => -$original->nominal,
                'tanggal' => now('Asia/Jakarta')->toDateString(),
                'keterangan' => 'Pembalikan '.$payment->nomor_bukti.': '.$reason,
                'membalik_id' => $original->id,
                'dicatat_oleh_id' => $pengurus->id,
            ]);
            RiwayatTransaksiKas::create([
                'transaksi_kas_id' => $reversal->id,
                'diubah_oleh_id' => $pengurus->id,
                'aksi' => 'dibalik',
                'sebelum' => ['transaksi_kas_id' => $original->id, 'nominal' => $original->nominal],
                'sesudah' => ['nominal' => $reversal->nominal],
                'alasan' => $reason,
            ]);

            $payment->forceFill([
                'status' => 'dibatalkan',
                'dibatalkan_oleh_id' => $pengurus->id,
                'dibatalkan_at' => now(),
                'alasan_pembatalan' => $reason,
            ])->save();

            Audit::record($pengurus, 'pembayaran_iuran.dibalik', 'pembayaran_iuran', $payment->id, ['alasan' => $reason]);
            Outbox::record('iuran.pembayaran_dibalik', 'pembayaran_iuran', $payment->id, ['rumah_id' => $payment->rumah_id]);

            return $payment->refresh()->load('alokasi.tagihan', 'rumah');
        });
    }

    /** Koreksi atomik: balik pembayaran asal lalu catat pengganti; gagal satu → semua batal. */
    public static function correctPayment(Akun $pengurus, string $paymentId, string $reason, array $periods, int $amount, string $paidOn, ?string $note): PembayaranIuran
    {
        return DB::transaction(function () use ($pengurus, $paymentId, $reason, $periods, $amount, $paidOn, $note) {
            $original = self::reversePayment($pengurus, $paymentId, $reason);

            return self::recordPayment($pengurus, $original->rumah_id, $periods, $amount, $paidOn, $note, $original->id);
        });
    }

    /** Kas manual non-iuran: pemasukan lain, pengeluaran, saldo awal (sekali). */
    public static function recordCash(Akun $pengurus, string $jenis, int $amount, string $date, string $kategori, string $keterangan): TransaksiKas
    {
        if (! in_array($jenis, ['pemasukan', 'pengeluaran', 'saldo_awal'], true) || $amount <= 0) {
            throw new DomainConflict('INVALID_CASH_ENTRY', 'Jenis atau nominal kas tidak valid.', 422);
        }

        return DB::transaction(function () use ($pengurus, $jenis, $amount, $date, $kategori, $keterangan) {
            if ($jenis === 'saldo_awal' && TransaksiKas::where('jenis', 'saldo_awal')->exists()) {
                throw new DomainConflict('OPENING_BALANCE_EXISTS', 'Saldo awal kas sudah pernah dicatat.');
            }
            $entry = TransaksiKas::create([
                'jenis' => $jenis,
                'sumber' => 'lainnya',
                'kategori' => $kategori,
                'nominal' => $jenis === 'pengeluaran' ? -$amount : $amount,
                'tanggal' => $date,
                'keterangan' => $keterangan,
                'dicatat_oleh_id' => $pengurus->id,
            ]);
            RiwayatTransaksiKas::create([
                'transaksi_kas_id' => $entry->id,
                'diubah_oleh_id' => $pengurus->id,
                'aksi' => 'dicatat',
                'sesudah' => ['jenis' => $jenis, 'nominal' => $entry->nominal],
                'alasan' => $keterangan,
            ]);
            Audit::record($pengurus, 'transaksi_kas.dicatat', 'transaksi_kas', $entry->id, ['jenis' => $jenis, 'nominal' => $entry->nominal]);

            return $entry;
        });
    }

    public static function reverseCash(Akun $pengurus, string $entryId, string $reason): TransaksiKas
    {
        return DB::transaction(function () use ($pengurus, $entryId, $reason) {
            $entry = TransaksiKas::whereKey($entryId)->lockForUpdate()->first();
            if (! $entry) {
                throw new DomainConflict('CASH_NOT_FOUND', 'Transaksi kas tidak ditemukan.', 404);
            }
            if ($entry->sumber === 'iuran' || $entry->jenis === 'pembalikan') {
                throw new DomainConflict('CASH_NOT_REVERSIBLE', 'Kas iuran dibatalkan melalui pembatalan pembayaran; pembalikan tidak dapat dibalik lagi.', 422);
            }
            if (TransaksiKas::where('membalik_id', $entry->id)->exists()) {
                throw new DomainConflict('CASH_ALREADY_REVERSED', 'Transaksi kas ini sudah dibalik.');
            }
            $reversal = TransaksiKas::create([
                'jenis' => 'pembalikan',
                'sumber' => 'lainnya',
                'kategori' => $entry->kategori,
                'nominal' => -$entry->nominal,
                'tanggal' => now('Asia/Jakarta')->toDateString(),
                'keterangan' => 'Pembalikan: '.$reason,
                'membalik_id' => $entry->id,
                'dicatat_oleh_id' => $pengurus->id,
            ]);
            RiwayatTransaksiKas::create([
                'transaksi_kas_id' => $reversal->id,
                'diubah_oleh_id' => $pengurus->id,
                'aksi' => 'dibalik',
                'sebelum' => ['transaksi_kas_id' => $entry->id, 'nominal' => $entry->nominal],
                'sesudah' => ['nominal' => $reversal->nominal],
                'alasan' => $reason,
            ]);
            Audit::record($pengurus, 'transaksi_kas.dibalik', 'transaksi_kas', $entry->id, ['alasan' => $reason]);

            return $reversal;
        });
    }

    public static function balance(): int
    {
        return (int) TransaksiKas::sum('nominal');
    }

    private static function receiptNumber(): string
    {
        // Sequence Postgres: aman untuk pencatatan bersamaan oleh beberapa pengurus.
        $seq = DB::selectOne("select nextval('pembayaran_nomor_seq') as n")->n;

        return sprintf('IUR-%s-%06d', now('Asia/Jakarta')->format('Y'), $seq);
    }
}
