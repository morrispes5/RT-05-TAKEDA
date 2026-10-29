<?php

namespace App\Domain\Finance;

use Illuminate\Support\Facades\DB;

/** One immutable snapshot for JSON/CSV/XLSX; charge settlement is current, cash history follows posting dates. */
final class FinanceReports
{
    public static function year(int $year): array
    {
        return DB::transaction(function () use ($year) {
            FlexibleDues::lock();
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-01-01', $year + 1);
            $charges = collect(self::charges($start, $end));
            $cash = collect(self::cash($start, $end));
            $opening = (int) DB::table('transaksi_kas')->where('tanggal', '<', $start)->sum('nominal');
            $uses = DB::table('penggunaan_titipan')->selectRaw("to_char(tanggal,'YYYY-MM') AS periode, sum(nominal) AS nominal")
                ->where('tanggal', '>=', $start)->where('tanggal', '<', $end)->groupByRaw("to_char(tanggal,'YYYY-MM')")->pluck('nominal', 'periode');
            $reversals = DB::table('penggunaan_titipan')->whereNotNull('dibatalkan_at')
                ->selectRaw("to_char(dibatalkan_at AT TIME ZONE 'Asia/Jakarta','YYYY-MM') AS periode, sum(nominal) AS nominal")
                ->groupByRaw("to_char(dibatalkan_at AT TIME ZONE 'Asia/Jakarta','YYYY-MM')")->pluck('nominal', 'periode');
            $months = [];
            for ($month = 1; $month <= 12; $month++) {
                $period = sprintf('%04d-%02d', $year, $month);
                $entries = $cash->where('periode', $period)->values();
                $bills = $charges->where('periode', $period)->values();
                $summary = CashSummary::fromEntries($period, $opening, $entries->all());
                $opening = $summary['saldo_akhir'];
                $months[] = ['periode' => $period, 'kas' => $summary,
                    'iuran_diterima' => (int) $entries->sum('iuran'), 'sumbangan_diterima' => (int) $entries->sum('sumbangan'),
                    'titipan_diterima' => (int) $entries->sum('titipan'),
                    'titipan_dipakai' => (int) ($uses[$period] ?? 0) - (int) ($reversals[$period] ?? 0),
                    'tagihan' => self::settlement($bills->all()), 'rumah' => $bills->all(), 'mutasi_kas' => $entries->all()];
            }
            $totals = ['saldo_awal' => $months[0]['kas']['saldo_awal'], 'saldo_akhir' => $months[11]['kas']['saldo_akhir']];
            foreach (['modal_awal', 'pemasukan', 'pengeluaran', 'koreksi'] as $field) {
                $totals[$field] = array_sum(array_column(array_column($months, 'kas'), $field));
            }
            foreach (['iuran_diterima', 'sumbangan_diterima', 'titipan_diterima', 'titipan_dipakai'] as $field) {
                $totals[$field] = array_sum(array_column($months, $field));
            }

            return ['tahun' => $year, 'posisi_pelunasan' => now('UTC')->toIso8601String(),
                'catatan' => 'Arus kas mengikuti tanggal posting. Pelunasan tagihan adalah posisi saat laporan dibuat, bukan snapshot akhir bulan lampau.',
                'total' => $totals, 'tagihan' => self::settlement($charges->all()),
                'saldo_titipan_saat_ini' => (int) DB::table('rt05_titipan_saldo')->sum('saldo'), 'bulan' => $months];
        });
    }

    public static function charges(?string $start = null, ?string $end = null): array
    {
        return DB::table('rt05_tagihan_saldo as t')->join('rumah as r', 'r.id', '=', 't.rumah_id')
            ->when($start, fn ($q) => $q->where('t.periode', '>=', $start))->when($end, fn ($q) => $q->where('t.periode', '<', $end))
            ->orderBy('t.periode')->orderBy('r.kode_rumah')->get(['t.*', 'r.kode_rumah', 'r.archived_at'])->map(function ($t) {
                [$status, $label] = self::settlementStatus($t);

                return ['rumah_id' => $t->rumah_id, 'kode_rumah' => $t->kode_rumah, 'rumah_arsip' => $t->archived_at !== null,
                    'periode' => substr($t->periode, 0, 7), 'nominal_snapshot' => (int) $t->nominal_tagihan, 'nominal_efektif' => (int) $t->nominal_efektif,
                    'terbayar' => self::paid($t), 'terbayar_titipan' => (int) $t->terbayar_titipan, 'terbayar_buku_lama' => (int) $t->terbayar_buku_lama,
                    'keringanan' => (int) $t->keringanan, 'sisa' => (int) $t->sisa, 'status' => $t->status, 'status_pelunasan' => $status, 'status_label' => $label];
            })->all();
    }

    /** Terbayar = uang + titipan + buku lama (ADR49); keringanan terpisah. Sumber tunggal API, CSV, dan XLSX. */
    public static function paid(object $saldo): int
    {
        return (int) $saldo->terbayar_uang + (int) $saldo->terbayar_titipan + (int) $saldo->terbayar_buku_lama;
    }

    /** @return array{0:string, 1:string} status_pelunasan (enum tetap empat nilai) dan label tampilan */
    public static function settlementStatus(object $saldo): array
    {
        $paid = self::paid($saldo);
        $status = (int) $saldo->sisa === 0 ? ((int) $saldo->keringanan > 0 ? 'lunas_keringanan' : 'lunas')
            : ($paid + (int) $saldo->keringanan > 0 ? 'kurang_bayar' : 'belum_bayar');
        $label = ['lunas' => 'Lunas', 'lunas_keringanan' => 'Lunas dengan keringanan', 'kurang_bayar' => 'Kurang bayar', 'belum_bayar' => 'Belum dibayar'][$status];
        if ($status === 'lunas' && (int) $saldo->terbayar_buku_lama > 0 && $paid === (int) $saldo->terbayar_buku_lama) {
            $label = 'Lunas (buku lama)';
        }

        return [$status, $label];
    }

    public static function cash(?string $start = null, ?string $end = null): array
    {
        return DB::table('transaksi_kas as k')->leftJoin('transaksi_kas as asal', 'asal.id', '=', 'k.membalik_id')
            ->leftJoin('pembayaran_iuran as p', 'p.id', '=', DB::raw('COALESCE(k.pembayaran_id,asal.pembayaran_id)'))
            ->when($start, fn ($q) => $q->where('k.tanggal', '>=', $start))->when($end, fn ($q) => $q->where('k.tanggal', '<', $end))
            ->orderBy('k.tanggal')->orderBy('k.created_at')->orderBy('k.id')
            ->get(['k.*', 'p.nomor_bukti', 'p.total_bayar', 'p.nominal_sumbangan', 'p.nominal_titipan'])->map(function ($k) {
                $sign = $k->jenis === 'pembalikan' ? -1 : 1;
                $donation = $sign * (int) ($k->nominal_sumbangan ?? 0);
                $deposit = $sign * (int) ($k->nominal_titipan ?? 0);
                $dues = $sign * ((int) ($k->total_bayar ?? 0) - (int) ($k->nominal_sumbangan ?? 0) - (int) ($k->nominal_titipan ?? 0));

                return ['id' => $k->id, 'periode' => substr($k->tanggal, 0, 7), 'tanggal' => $k->tanggal, 'jenis' => $k->jenis,
                    'sumber' => $k->sumber, 'kategori' => $k->kategori, 'nominal' => (int) $k->nominal, 'keterangan' => $k->keterangan,
                    'nomor_bukti' => $k->nomor_bukti, 'iuran' => $dues, 'sumbangan' => $donation, 'titipan' => $deposit, 'membalik_id' => $k->membalik_id];
            })->all();
    }

    private static function settlement(array $rows): array
    {
        $result = [];
        foreach (['nominal_snapshot', 'nominal_efektif', 'terbayar', 'terbayar_titipan', 'terbayar_buku_lama', 'keringanan', 'sisa'] as $field) {
            $result[$field] = array_sum(array_column($rows, $field));
        }

        return $result;
    }

    public static function summaryRows(array $report): array
    {
        return array_map(fn ($month) => [$month['periode'], $month['kas']['saldo_awal'], $month['kas']['modal_awal'],
            $month['kas']['pemasukan'], $month['kas']['pengeluaran'], $month['kas']['koreksi'], $month['kas']['saldo_akhir'],
            $month['iuran_diterima'], $month['sumbangan_diterima'], $month['titipan_diterima'], $month['titipan_dipakai'],
            $month['tagihan']['nominal_efektif'], $month['tagihan']['terbayar'], $month['tagihan']['keringanan'], $month['tagihan']['sisa']], $report['bulan']);
    }

    public const SUMMARY_HEADER = ['Bulan', 'Saldo awal bulan', 'Modal awal', 'Uang masuk', 'Uang keluar', 'Koreksi kas', 'Saldo akhir bulan',
        'Bagian iuran neto', 'Sumbangan neto', 'Titipan diterima neto', 'Titipan dipakai neto', 'Tagihan efektif', 'Terbayar saat ini', 'Keringanan saat ini', 'Sisa saat ini'];
}
