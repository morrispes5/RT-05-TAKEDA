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
        $entries = DB::table('transaksi_kas')->where('tanggal', '>=', $start)->where('tanggal', '<', $end)->get(['jenis', 'nominal'])
            ->map(fn ($row) => ['jenis' => $row->jenis, 'nominal' => (int) $row->nominal])->all();

        return self::fromEntries($period, $opening, $entries);
    }

    public static function fromEntries(string $period, int $opening, array $entries): array
    {
        $totals = ['saldo_awal' => 0, 'pemasukan' => 0, 'pengeluaran' => 0, 'pembalikan' => 0];
        foreach ($entries as $entry) {
            $totals[$entry['jenis']] += $entry['nominal'];
        }

        return ['periode' => $period, 'saldo_awal' => $opening, 'modal_awal' => $totals['saldo_awal'],
            'pemasukan' => $totals['pemasukan'], 'pengeluaran' => -$totals['pengeluaran'], 'koreksi' => $totals['pembalikan'],
            'saldo_akhir' => $opening + array_sum($totals)];
    }
}
