<?php

namespace App\Domain\Finance;

use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** Writes a previously captured report snapshot; no database locks remain during ZIP rendering/download. */
final class FinanceWorkbook
{
    private const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** Safety net for interrupted downloads; only UUID-named generated files in the fixed private directory. */
    public static function prune(): int
    {
        $root = storage_path('app/private/finance');
        if (! is_dir($root) || is_link($root)) {
            return 0;
        }
        $deleted = 0;
        foreach (new \DirectoryIterator($root) as $file) {
            if ($file->isLink() || ! $file->isFile() || ! preg_match('/^[a-f0-9-]{36}\.xlsx$/D', $file->getFilename())) {
                continue;
            }
            if ($file->getMTime() < now()->subDay()->getTimestamp() && unlink($file->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function write(array $report, string $file): void
    {
        $options = new Options;
        $options->setTempFolder(dirname($file));
        $options->DEFAULT_COLUMN_WIDTH = 21;
        $options->DEFAULT_ROW_STYLE = (new Style)->setFontName('Calibri')->setFontSize(11)->setFontColor('183B50')->setShouldWrapText();
        $writer = new Writer($options);
        $writer->setCreator('RT05 TAKEDA');
        $writer->openToFile($file);
        try {
            $sheet = $writer->getCurrentSheet()->setName('Rekap Tahun');
            self::heading($writer, $sheet, $report, 'Laporan keuangan '.$report['tahun'], count(FinanceReports::SUMMARY_HEADER));
            self::row($writer, FinanceReports::SUMMARY_HEADER, true);
            foreach (FinanceReports::summaryRows($report) as $row) {
                self::row($writer, $row);
            }
            $t = $report['total'];
            $c = $report['tagihan'];
            self::row($writer, ['TOTAL / POSISI TAHUN', $t['saldo_awal'], $t['modal_awal'], $t['pemasukan'], $t['pengeluaran'], $t['koreksi'], $t['saldo_akhir'],
                $t['iuran_diterima'], $t['sumbangan_diterima'], $t['titipan_diterima'], $t['titipan_dipakai'], $c['nominal_efektif'], $c['terbayar'], $c['keringanan'], $c['sisa']], true);
            $sheet->setAutoFilter(new AutoFilter(0, 4, count(FinanceReports::SUMMARY_HEADER) - 1, 16));

            $sheet = $writer->addNewSheetAndMakeItCurrent()->setName('Rumah x Bulan');
            $headers = ['Kode rumah', 'Rumah arsip'];
            foreach (self::MONTHS as $month) {
                foreach (['Tagihan', 'Terbayar', 'Keringanan', 'Sisa', 'Status'] as $field) {
                    $headers[] = $month.' — '.$field;
                }
            }
            self::heading($writer, $sheet, $report, 'Penyelesaian rumah × bulan '.$report['tahun'], count($headers));
            self::row($writer, $headers, true);
            $houses = [];
            foreach ($report['bulan'] as $month) {
                foreach ($month['rumah'] as $house) {
                    $houses[$house['rumah_id']]['kode'] = $house['kode_rumah'];
                    $houses[$house['rumah_id']]['arsip'] = $house['rumah_arsip'];
                    $houses[$house['rumah_id']]['bulan'][$month['periode']] = $house;
                }
            }
            uasort($houses, fn ($a, $b) => strnatcmp($a['kode'], $b['kode']));
            foreach ($houses as $house) {
                $row = [$house['kode'], $house['arsip'] ? 'Ya' : 'Tidak'];
                foreach ($report['bulan'] as $month) {
                    $charge = $house['bulan'][$month['periode']] ?? null;
                    array_push($row, $charge['nominal_efektif'] ?? null, $charge['terbayar'] ?? null, $charge['keringanan'] ?? null, $charge['sisa'] ?? null, $charge['status_label'] ?? 'Tidak ditagih');
                }
                self::row($writer, $row);
            }
            $sheet->setAutoFilter(new AutoFilter(0, 4, count($headers) - 1, 4 + count($houses)));
            $sheet->setSheetView((new SheetView)->setFreezeRow(5)->setFreezeColumn('C'));
            $sheet->setColumnWidth(24, 1);

            foreach ($report['bulan'] as $index => $month) {
                $sheet = $writer->addNewSheetAndMakeItCurrent()->setName(sprintf('%02d %s', $index + 1, self::MONTHS[$index]));
                self::heading($writer, $sheet, $report, self::MONTHS[$index].' '.$report['tahun'].' — mutasi kas dan tagihan', 9);
                self::row($writer, ['Tanggal', 'Jenis', 'Kategori', 'Nominal kas', 'Keterangan', 'Nomor bukti', 'Bagian iuran', 'Sumbangan', 'Titipan'], true);
                foreach ($month['mutasi_kas'] as $cash) {
                    self::row($writer, [$cash['tanggal'], $cash['jenis'], $cash['kategori'], $cash['nominal'], $cash['keterangan'], $cash['nomor_bukti'], $cash['iuran'], $cash['sumbangan'], $cash['titipan']], false,
                        max(30, min(180, (int) ceil(mb_strlen($cash['keterangan']) / 45) * 15)));
                }
                $sheet->setAutoFilter(new AutoFilter(0, 4, 8, 4 + count($month['mutasi_kas'])));
                if ($month['mutasi_kas'] === []) {
                    self::row($writer, ['Belum ada mutasi kas pada bulan ini.']);
                }
                self::row($writer, []);
                self::row($writer, ['Ringkasan kas', 'Rupiah'], true);
                foreach (['saldo_awal' => 'Saldo awal bulan', 'modal_awal' => 'Modal awal', 'pemasukan' => 'Uang masuk', 'pengeluaran' => 'Uang keluar', 'koreksi' => 'Koreksi kas', 'saldo_akhir' => 'Saldo akhir bulan'] as $field => $label) {
                    self::row($writer, [$label, $month['kas'][$field]]);
                }
                self::row($writer, ['Titipan dipakai neto', $month['titipan_dipakai']]);
                self::row($writer, []);
                self::row($writer, ['Posisi tagihan saat laporan dibuat'], true);
                self::row($writer, ['Kode rumah', 'Bulan', 'Snapshot tagihan', 'Tagihan efektif', 'Terbayar', 'Dari titipan', 'Keringanan', 'Sisa', 'Status'], true);
                foreach ($month['rumah'] as $house) {
                    self::row($writer, [$house['kode_rumah'], $house['periode'], $house['nominal_snapshot'], $house['nominal_efektif'], $house['terbayar'], $house['terbayar_titipan'], $house['keringanan'], $house['sisa'], $house['status_label']]);
                }
                $sheet->setColumnWidth(16, 1, 2);
                $sheet->setColumnWidth(44, 5);
                $sheet->setColumnWidth(27, 6);
            }
        } finally {
            $writer->close();
        }
    }

    private static function heading(Writer $writer, Sheet $sheet, array $report, string $title, int $columns): void
    {
        $options = $writer->getOptions();
        foreach ([1, 2, 3] as $row) {
            $options->mergeCells(0, $row, $columns - 1, $row, $sheet->getIndex());
        }
        self::row($writer, [$title], true, 32);
        $time = CarbonImmutable::parse($report['posisi_pelunasan'])->setTimezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB';
        self::row($writer, ['Dibuat '.$time.' • Rupiah utuh • Khusus pengurus'], false, 25);
        self::row($writer, [$report['catatan']], false, 38);
        $sheet->setSheetView((new SheetView)->setFreezeRow(5)->setFreezeColumn('B'));
        $sheet->setColumnWidthForRange(21, 1, $columns);
    }

    private static function row(Writer $writer, array $values, bool $header = false, int $height = 30): void
    {
        $rowStyle = (new Style)->setShouldWrapText();
        if ($header) {
            $rowStyle->setBackgroundColor('183B50')->setFontColor('FFD84D')->setFontBold();
        }
        $moneyStyle = (new Style)->setFormat('"Rp" #,##0;[Red]("Rp" #,##0)');
        $cells = array_map(fn ($value) => is_int($value) ? new NumericCell($value, $moneyStyle) : new StringCell((string) ($value ?? ''), null), $values);
        $row = new Row($cells, $rowStyle);
        $row->setHeight($height);
        $writer->addRow($row);
    }
}
