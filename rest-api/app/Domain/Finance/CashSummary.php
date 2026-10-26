<?php

namespace App\Domain\Finance;

use Illuminate\Support\Facades\DB;

/** Aggregate allowlist, shared by resident transparency and the financial reports. No identities or free text. */
final class CashSummary
{
    public static function month(string $period): array
    {
        $date = Dues::period($period);
        $start = $date->toDateString();
        $end = $date->addMonth()->toDateString();
        $opening = (int) DB::table('transaksi_kas')->where('tanggal', '<', $start)->sum('nominal');
        $month = DB::table('transaksi_kas')->where('tanggal', '>=', $start)->where('tanggal', '<', $end)->selectRaw(<<<'SQL'
            COALESCE(sum(nominal) FILTER(WHERE jenis='saldo_awal'),0) AS modal,
            COALESCE(sum(nominal) FILTER(WHERE jenis='pemasukan'),0) AS masuk,
            -COALESCE(sum(nominal) FILTER(WHERE jenis='pengeluaran'),0) AS keluar,
            COALESCE(sum(nominal) FILTER(WHERE jenis='pembalikan'),0) AS koreksi,
            COALESCE(sum(nominal),0) AS bersih
            SQL)->first();

        return ['periode' => $period, 'saldo_awal' => $opening, 'modal_awal' => (int) $month->modal,
            'pemasukan' => (int) $month->masuk, 'pengeluaran' => (int) $month->keluar, 'koreksi' => (int) $month->koreksi,
            'saldo_akhir' => $opening + (int) $month->bersih];
    }
}
