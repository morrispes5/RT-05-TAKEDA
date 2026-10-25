<?php

namespace App\Domain\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi tagihan ↔ pembayaran ↔ alokasi ↔ buku kas (M12). Hanya membaca dan menghitung
 * pelanggaran; tidak memperbaiki otomatis (koreksi keuangan selalu lewat pembalikan oleh pengurus).
 * Keluaran hanya angka, tanpa nama/kode rumah, sehingga aman untuk log scheduler.
 */
final class Reconciliation
{
    /** @return array{masalah: array<string, int>, total_masalah: int, saldo: int} */
    public static function run(): array
    {
        $count = fn (string $sql) => (int) DB::selectOne("SELECT count(*) AS n FROM ($sql) x")->n;

        $issues = [
            // Status tagihan harus cocok dengan alokasi aktif (indeks unik menjamin paling banyak satu).
            'lunas_tanpa_alokasi' => $count("SELECT id FROM rt05_tagihan_saldo WHERE status='lunas' AND sisa<>0"),
            'belum_bayar_dengan_alokasi' => $count("SELECT id FROM rt05_tagihan_saldo WHERE status='belum_bayar' AND sisa=0"),
            'alokasi_beda_nominal' => $count('SELECT id FROM rt05_tagihan_saldo WHERE sisa<0'),
            'alokasi_lintas_rumah' => $count('SELECT a.id FROM alokasi_pembayaran a JOIN tagihan_iuran t ON t.id = a.tagihan_id
                JOIN pembayaran_iuran p ON p.id = a.pembayaran_id WHERE t.rumah_id <> p.rumah_id'),
            // Pembayaran tercatat: alokasi aktif = total, tepat satu kas pemasukan iuran bernominal sama.
            'pembayaran_tercatat_selisih_alokasi' => $count("SELECT p.id FROM pembayaran_iuran p WHERE p.status = 'tercatat'
                AND p.total_bayar <> p.nominal_sumbangan + p.nominal_titipan + COALESCE((SELECT sum(a.nominal_alokasi) FROM alokasi_pembayaran a WHERE a.pembayaran_id = p.id AND a.dibatalkan_at IS NULL), 0)"),
            'pembayaran_tanpa_kas' => $count("SELECT p.id FROM pembayaran_iuran p
                WHERE NOT EXISTS (SELECT 1 FROM transaksi_kas k WHERE k.pembayaran_id = p.id AND k.sumber = 'iuran' AND k.jenis = 'pemasukan' AND k.nominal = p.total_bayar)"),
            // Pembayaran dibatalkan: tanpa alokasi aktif dan kas asalnya sudah dibalik.
            'pembayaran_batal_alokasi_aktif' => $count("SELECT p.id FROM pembayaran_iuran p WHERE p.status = 'dibatalkan'
                AND EXISTS (SELECT 1 FROM alokasi_pembayaran a WHERE a.pembayaran_id = p.id AND a.dibatalkan_at IS NULL)"),
            'pembayaran_batal_tanpa_pembalikan' => $count("SELECT p.id FROM pembayaran_iuran p JOIN transaksi_kas k ON k.pembayaran_id = p.id
                WHERE p.status = 'dibatalkan' AND NOT EXISTS (SELECT 1 FROM transaksi_kas r WHERE r.membalik_id = k.id AND r.nominal = -k.nominal)"),
            'pembayaran_tercatat_kas_dibalik' => $count("SELECT p.id FROM pembayaran_iuran p JOIN transaksi_kas k ON k.pembayaran_id = p.id
                WHERE p.status = 'tercatat' AND EXISTS (SELECT 1 FROM transaksi_kas r WHERE r.membalik_id = k.id)"),
            // Kas iuran hanya lewat pembayaran (BR16); pembalikan menetralkan nominal asal.
            'kas_iuran_tanpa_pembayaran' => $count("SELECT k.id FROM transaksi_kas k WHERE k.sumber = 'iuran' AND k.jenis = 'pemasukan' AND k.pembayaran_id IS NULL"),
            'pembalikan_tidak_netral' => $count('SELECT r.id FROM transaksi_kas r JOIN transaksi_kas k ON k.id = r.membalik_id WHERE r.nominal <> -k.nominal'),
            'saldo_awal_ganda' => max(0, (int) DB::table('transaksi_kas')->where('jenis', 'saldo_awal')->count() - 1),
        ];

        foreach (DB::table('rt05_finance_violations')->selectRaw('jenis, count(*) AS jumlah')->groupBy('jenis')->get() as $violation) {
            $issues['m18_'.$violation->jenis] = (int) $violation->jumlah;
        }

        return ['masalah' => $issues, 'total_masalah' => array_sum($issues), 'saldo' => Dues::balance()];
    }
}
