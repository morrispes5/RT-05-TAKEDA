<?php

namespace App\Console\Commands;

use App\Domain\Finance\OldBook;
use App\Exceptions\DomainConflict;
use App\Models\Akun;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * ADR49: impor rekap iuran Excel bendahara sebagai "lunas di buku lama".
 *
 * Tanpa --terapkan hanya cek (tidak menulis database). Berkas Excel, keputusan, dan laporan tinjauan memuat data rumah:
 * simpan di PC/server pengurus saja, jangan di Git/CI/chat. Keluaran konsol hanya kode rumah dan angka.
 */
class ImportOldBook extends Command
{
    protected $signature = 'rt05:impor-buku-lama
        {berkas : Excel rekap bendahara (.xlsx)}
        {--tahun= : Tahun rekap, misalnya 2026}
        {--sampai= : Bulan terakhir buku lama (YYYY-MM); bulan sesudahnya hanya dibaca bila sudah dibayar di muka}
        {--sheet= : Nama sheet (bawaan "IURAN <tahun>")}
        {--keputusan= : Berkas JSON keputusan tarif/tidak ditagih}
        {--laporan= : Tulis Excel tinjauan per rumah × bulan ke path ini}
        {--terapkan : Tulis ke database (wajib --pengurus dan --sidik-jari hasil cek)}
        {--pengurus= : Email pengurus aktif pelaksana}
        {--sidik-jari= : Sidik jari rencana dari hasil cek yang sudah ditinjau bendahara}';

    protected $description = 'Cek atau terapkan impor rekap iuran bendahara (lunas di buku lama, tanpa kas)';

    public function handle(): int
    {
        $year = (int) $this->option('tahun');
        $until = (string) $this->option('sampai');
        if ($year < 2020 || $year > 2100 || $until === '') {
            $this->error('Wajib --tahun=YYYY dan --sampai=YYYY-MM.');

            return self::FAILURE;
        }
        try {
            $rows = OldBook::read((string) $this->argument('berkas'), (string) ($this->option('sheet') ?: "IURAN $year"));
            $plan = OldBook::plan($rows, OldBook::decisions($this->option('keputusan')), $year, $until, OldBook::commonRate($year));
            foreach ([OldBook::rateIssue($year), OldBook::periodIssue($until)] as $issue) {
                if ($issue !== null) {
                    $plan['perlu_keputusan'][] = ['baris' => null, 'kode' => null, 'pesan' => $issue];
                }
            }
            $this->report($plan);
            if ($path = $this->option('laporan')) {
                $this->workbook($plan, (string) $path);
                $this->line("Laporan tinjauan ditulis: $path (data rumah — simpan lokal, jangan dibagikan ke grup).");
            }
            if (! $this->option('terapkan')) {
                $this->info('Cek selesai. Database tidak diubah.');

                return $plan['perlu_keputusan'] === [] ? self::SUCCESS : self::FAILURE;
            }

            return $this->apply($plan);
        } catch (DomainConflict $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function apply(array $plan): int
    {
        if ($this->option('sidik-jari') !== $plan['sidik_jari']) {
            $this->error('Sidik jari tidak cocok dengan rencana ini. Jalankan cek, tinjau laporan, lalu ulangi dengan --sidik-jari hasil cek.');

            return self::FAILURE;
        }
        $actor = Akun::where('email', strtolower(trim((string) $this->option('pengurus'))))->first();
        if (! $actor) {
            $this->error('Akun pengurus tidak ditemukan.');

            return self::FAILURE;
        }
        try {
            $result = OldBook::apply($actor, $plan);
        } catch (QueryException $e) {
            // Invarian keuangan (trigger commit) menolak; tidak ada yang tersimpan sebagian.
            $this->error('Database menolak impor (invarian keuangan). Tidak ada perubahan tersimpan. Kode: '.($e->errorInfo[0] ?? '?'));

            return self::FAILURE;
        }
        $this->info("Impor diterapkan: {$result['rumah']} rumah ({$result['rumah_baru']} baru), {$result['bulan_lunas']} bulan lunas buku lama.");
        $this->line('Berikutnya: bendahara mencatat saldo awal kas resmi di aplikasi, lalu jalankan rekonsiliasi.');

        return self::SUCCESS;
    }

    private function report(array $plan): void
    {
        $s = $plan['ringkasan'];
        $rp = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.');
        $this->line("Rekap {$plan['tahun']} sampai {$plan['sampai']}, tarif umum {$rp($plan['tarif_umum'])}");
        $this->table(['Ringkasan', 'Nilai'], [
            ['Rumah diimpor', $s['rumah']], ['Rumah dilewati (tidak ditagih)', $s['rumah_dilewati']], ['Tarif khusus', $s['tarif_khusus']],
            ['Tagihan', $s['tagihan']], ['Bulan lunas', $s['bulan_lunas']], ['Bulan kurang bayar', $s['bulan_kurang']],
            ['Bulan belum dibayar', $s['bulan_belum']], ['Bulan dibayar di muka', $s['bulan_di_muka']],
            ['Total tagihan', $rp($s['nominal_tagihan'])], ['Lunas di buku lama', $rp($s['nominal_buku_lama'])],
            ['Sisa tunggakan', $rp($s['nominal_sisa'])], ['Kelebihan bayar (sudah di kas fisik)', $rp($s['nominal_lebih'])],
            ['Kolom sumbangan (informasi)', $rp($s['sumbangan_kolom'])],
        ]);
        if ($plan['perlu_keputusan'] !== []) {
            $this->warn('Perlu keputusan sebelum impor ('.count($plan['perlu_keputusan']).'):');
            foreach ($plan['perlu_keputusan'] as $item) {
                $this->line('  - '.($item['kode'] ?? '-').($item['baris'] ? " (baris {$item['baris']})" : '').": {$item['pesan']}");
            }
        }
        if ($plan['catatan'] !== []) {
            $this->line('Bulan dengan nominal berbeda dari tarif: '.count($plan['catatan']).' (rincian di --laporan).');
        }
        $this->line("Sidik jari rencana: {$plan['sidik_jari']}");
    }

    private function workbook(array $plan, string $path): void
    {
        $until = (int) substr($plan['sampai'], 5, 2);
        $writer = new Writer;
        $writer->openToFile($path);
        try {
            $writer->getCurrentSheet()->setName('Rumah x Bulan');
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $writer->addRow(Row::fromValues(array_merge(['Kode', 'Jalan', 'Tarif', 'Jenis tarif'], $months, ['Lunas buku lama', 'Sisa', 'Lebih'])));
            foreach ($plan['rumah'] as $house) {
                if ($house['lewati']) {
                    $writer->addRow(Row::fromValues([$house['kode'], $house['jalan'], 'Tidak ditagih']));

                    continue;
                }
                $cells = [$house['kode'], $house['jalan'], $house['tarif']['nominal'], $house['tarif']['mode']];
                $per = OldBook::months($house, $until);
                $paid = $rest = $more = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $month = $per[$m] ?? null;
                    $cells[] = $month === null ? '' : ($month['sisa'] === 0 ? 'Lunas' : ($month['buku_lama'] === 0 ? 'Belum' : 'Kurang '.$month['sisa']));
                    $paid += $month['buku_lama'] ?? 0;
                    $rest += $month['sisa'] ?? 0;
                    $more += $month['lebih'] ?? 0;
                }
                $writer->addRow(Row::fromValues(array_merge($cells, [$paid, $rest, $more])));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Perlu keputusan');
            $writer->addRow(Row::fromValues(['Kode', 'Baris', 'Pesan']));
            foreach ($plan['perlu_keputusan'] as $item) {
                $writer->addRow(Row::fromValues([(string) $item['kode'], (string) $item['baris'], $item['pesan']]));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Nominal berbeda');
            $writer->addRow(Row::fromValues(['Kode', 'Periode', 'Jenis', 'Dibayar', 'Tarif']));
            foreach ($plan['catatan'] as $note) {
                $writer->addRow(Row::fromValues([$note['kode'], $note['periode'], $note['jenis'], $note['nominal'], $note['tarif']]));
            }
        } finally {
            $writer->close();
        }
    }
}
