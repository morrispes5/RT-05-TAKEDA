<?php

namespace App\Domain\Finance;

use App\Domain\Residents\Address;
use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\Rumah;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * ADR49: impor rekap iuran Excel bendahara sebagai "lunas di buku lama" (tanpa kas; uangnya masuk lewat saldo awal).
 *
 * read() hanya membaca alamat dan angka iuran; kolom NIK/KK/nama tidak pernah disalin ke hasil. plan() murni (tanpa tulis),
 * apply() satu transaksi dengan kunci rt05:keuangan. Nominal yang berbeda dari tarif tidak ditebak: perlu berkas keputusan.
 */
final class OldBook
{
    // Tata letak sheet rekap bendahara (kolom 0-based): F jalan, G blok, I nomor, P kondisi, Q.. iuran (selang-seling sumbangan).
    private const COL_STREET = 5;

    private const COL_BLOCK = 6;

    private const COL_NUMBER = 8;

    private const COL_FIRST_MONTH = 16;

    private const HEADER_ROW = 4;

    private const FIRST_DATA_ROW = 6;

    private const MONTH_NAMES = [['JAN'], ['FEB'], ['MAR'], ['APR'], ['MAY', 'MEI'], ['JUN'], ['JUL'], ['AUG', 'AGU', 'AGT', 'AGS'],
        ['SEP'], ['OCT', 'OKT'], ['NOV'], ['DEC', 'DES']];

    private const DECISION_KEYS = ['tarif', 'tarif_umum', 'tidak_ditagih'];

    /**
     * Baris TOTAL di bawah tabel (rumus jumlah per bulan) dipakai sebagai cek silang hasil baca, lalu pembacaan berhenti.
     *
     * @return array{rumah: list<array{baris:int, jalan:string, blok:string, nomor:string, iuran:list<int>, sumbangan:int}>, total: ?list<int>}
     */
    public static function read(string $path, string $sheetName): array
    {
        if (! is_file($path)) {
            throw new DomainConflict('IMPORT_FILE_NOT_FOUND', 'Berkas Excel tidak ditemukan.', 422);
        }
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if (trim($sheet->getName()) !== trim($sheetName)) {
                    continue;
                }
                $rows = [];
                $total = null;
                $header = false;
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(fn ($cell) => $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue(), $row->getCells());
                    if ($index === self::HEADER_ROW) {
                        self::checkHeader($values);
                        $header = true;
                    }
                    if ($index < self::FIRST_DATA_ROW) {
                        continue;
                    }
                    $street = self::text($values[self::COL_STREET] ?? null);
                    $block = self::text($values[self::COL_BLOCK] ?? null);
                    $number = self::text($values[self::COL_NUMBER] ?? null);
                    if (preg_match('/^(total|jumlah)\b/i', $street) && $block === '' && $number === '') {
                        $total = array_map(fn ($m) => self::amount($values[self::COL_FIRST_MONTH + 2 * $m] ?? null, $index, PHP_INT_MAX), range(0, 11));

                        break;
                    }
                    if ($street === '' && $block === '' && $number === '') {
                        continue;
                    }
                    if ($street === '' || $block === '' || $number === '') {
                        throw new DomainConflict('IMPORT_ADDRESS_INCOMPLETE', "Baris $index: jalan, blok, dan nomor wajib lengkap.", 422);
                    }
                    $dues = [];
                    $donation = 0;
                    for ($m = 0; $m < 12; $m++) {
                        $dues[] = self::amount($values[self::COL_FIRST_MONTH + 2 * $m] ?? null, $index);
                        $donation += self::amount($values[self::COL_FIRST_MONTH + 2 * $m + 1] ?? null, $index);
                    }
                    $rows[] = ['baris' => $index, 'jalan' => $street, 'blok' => $block, 'nomor' => $number, 'iuran' => $dues, 'sumbangan' => $donation];
                }
                if (! $header) {
                    throw new DomainConflict('IMPORT_HEADER_INVALID', 'Baris judul bulan tidak ditemukan.', 422);
                }

                return ['rumah' => $rows, 'total' => $total];
            }
        } finally {
            $reader->close();
        }

        throw new DomainConflict('IMPORT_SHEET_NOT_FOUND', "Sheet \"$sheetName\" tidak ada di berkas.", 422);
    }

    private static function checkHeader(array $values): void
    {
        foreach (self::MONTH_NAMES as $m => $names) {
            $label = strtoupper(substr(self::text($values[self::COL_FIRST_MONTH + 2 * $m] ?? null), 0, 3));
            if (! in_array($label, $names, true)) {
                throw new DomainConflict('IMPORT_HEADER_INVALID', 'Susunan kolom bulan tidak sesuai rekap (Jan di kolom Q, selang-seling Iuran/Sumbangan).', 422);
            }
        }
    }

    private static function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return is_scalar($value) ? trim(preg_replace('/\s+/', ' ', (string) $value)) : '';
    }

    /** Nominal satu sel rumah dibatasi seperti tarif (≤10 juta); baris TOTAL memakai batas lebih longgar. */
    private static function amount(mixed $value, int $row, int $max = 10000000): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value) || (is_float($value) && floor($value) === $value)) {
            if ($value < 0 || $value > $max) {
                throw new DomainConflict('IMPORT_AMOUNT_INVALID', "Baris $row: nominal di luar batas.", 422);
            }

            return (int) $value;
        }

        throw new DomainConflict('IMPORT_AMOUNT_INVALID', "Baris $row: sel nominal berisi teks atau pecahan.", 422);
    }

    /** @return array{tarif: array<string, array{nominal:int, alasan:string}>, tarif_umum: list<string>, tidak_ditagih: list<string>} */
    public static function decisions(?string $path): array
    {
        $empty = ['tarif' => [], 'tarif_umum' => [], 'tidak_ditagih' => []];
        if ($path === null || $path === '') {
            return $empty;
        }
        if (! is_file($path)) {
            throw new DomainConflict('IMPORT_DECISIONS_INVALID', 'Berkas keputusan tidak ditemukan.', 422);
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || array_diff(array_keys($data), self::DECISION_KEYS) !== []) {
            throw new DomainConflict('IMPORT_DECISIONS_INVALID', 'Berkas keputusan harus JSON dengan kunci tarif, tarif_umum, tidak_ditagih.', 422);
        }
        $result = $empty;
        foreach ((array) ($data['tarif'] ?? []) as $code => $rate) {
            $nominal = $rate['nominal'] ?? null;
            $reason = trim((string) ($rate['alasan'] ?? ''));
            if (! is_int($nominal) || $nominal < 1000 || $nominal > 10000000 || mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
                throw new DomainConflict('IMPORT_DECISIONS_INVALID', "Keputusan tarif $code: nominal 1.000–10.000.000 dan alasan 5–300 karakter.", 422);
            }
            $result['tarif'][strtoupper((string) $code)] = ['nominal' => $nominal, 'alasan' => $reason];
        }
        foreach (['tarif_umum', 'tidak_ditagih'] as $key) {
            $list = $data[$key] ?? [];
            if (! is_array($list) || ! array_is_list($list)) {
                throw new DomainConflict('IMPORT_DECISIONS_INVALID', "Keputusan $key harus daftar kode rumah.", 422);
            }
            $result[$key] = array_values(array_unique(array_map(fn ($c) => strtoupper(trim((string) $c)), $list)));
        }
        $codes = array_merge(array_keys($result['tarif']), $result['tarif_umum'], $result['tidak_ditagih']);
        if (count($codes) !== count(array_unique($codes))) {
            throw new DomainConflict('IMPORT_DECISIONS_INVALID', 'Satu kode rumah hanya boleh ada di satu jenis keputusan.', 422);
        }

        return $result;
    }

    /** Tarif umum yang berlaku Januari tahun impor; bila belum ada, impor membuat versi Rp75.000 (BR10). */
    public static function commonRate(int $year): int
    {
        $rate = DB::table('tarif_iuran')->whereNull('dibatalkan_at')->where('mulai_periode', '<=', sprintf('%04d-01-01', $year))
            ->orderByDesc('mulai_periode')->value('nominal');

        return $rate === null ? 75000 : (int) $rate;
    }

    /** Batas impor melewati bulan berjalan akan memunculkan tunggakan bulan yang belum jatuh tempo di HP warga. */
    public static function periodIssue(string $until): ?string
    {
        return Dues::period($until) > Dues::period(now('Asia/Jakarta')->format('Y-m'))
            ? 'Batas impor (--sampai) tidak boleh melewati bulan berjalan; bulan yang dibayar di muka tetap terbaca.' : null;
    }

    /** Impor memakai satu tarif umum setahun; perubahan tarif umum di tengah tahun belum didukung (ditolak, bukan ditebak). */
    public static function rateIssue(int $year): ?string
    {
        $common = self::commonRate($year);
        $changed = DB::table('tarif_iuran')->whereNull('dibatalkan_at')->whereBetween('mulai_periode', [sprintf('%04d-02-01', $year), sprintf('%04d-12-01', $year)])
            ->where('nominal', '<>', $common)->exists();

        return $changed ? "Tarif umum $year berubah di tengah tahun; impor buku lama belum mendukung." : null;
    }

    /**
     * Rencana impor tanpa menulis database. `perlu_keputusan` kosong adalah syarat apply().
     *
     * @param  array{rumah: list<array>, total: ?list<int>}  $sheet  hasil read()
     */
    public static function plan(array $sheet, array $decisions, int $year, string $until, int $commonRate): array
    {
        $untilMonth = self::untilMonth($year, $until);
        $houses = [];
        $blocking = [];
        $notes = [];
        foreach ($sheet['total'] ?? [] as $m => $expected) {
            $read = array_sum(array_map(fn ($row) => $row['iuran'][$m], $sheet['rumah']));
            if ($read !== $expected) {
                $blocking[] = ['baris' => null, 'kode' => null, 'pesan' => sprintf('Jumlah iuran %04d-%02d hasil baca Rp%s ≠ baris TOTAL Excel Rp%s; periksa baris tersembunyi/terlewat.',
                    $year, $m + 1, number_format($read, 0, ',', '.'), number_format($expected, 0, ',', '.'))];
            }
        }
        foreach ($sheet['rumah'] as $row) {
            $street = Address::street($row['jalan']);
            if (! preg_match('/^Mawar (8|9|1[0-4])$/', $street)) {
                $blocking[] = ['baris' => $row['baris'], 'kode' => null, 'pesan' => 'Jalan di luar Mawar 8–14.'];

                continue;
            }
            $code = Address::code($row['blok'], $row['nomor']);
            $key = Address::key($row['blok'], $street, $row['nomor']);
            if (isset($houses[$code])) {
                $blocking[] = ['baris' => $row['baris'], 'kode' => $code, 'pesan' => 'Kode rumah ganda di sheet.'];

                continue;
            }
            $paid = array_values(array_filter($row['iuran'], fn ($v) => $v > 0));
            $counts = array_count_values($paid);
            $common = $counts[$commonRate] ?? 0;
            unset($counts[$commonRate]);
            arsort($counts);
            // Tarif umum harus mayoritas mutlak bulan terbayar; seri atau kalah berarti perlu keputusan, bukan tebakan.
            $other = $common <= count($paid) - $common && $counts !== [] ? (int) array_key_first($counts) : null;
            if (in_array($code, $decisions['tidak_ditagih'], true)) {
                $houses[$code] = ['kode' => $code, 'alamat_key' => $key, 'blok' => $row['blok'], 'jalan' => $street, 'nomor' => $row['nomor'],
                    'lewati' => true, 'tarif' => null, 'iuran' => $row['iuran'], 'sumbangan' => $row['sumbangan']];

                continue;
            }
            $rate = $decisions['tarif'][$code] ?? null;
            $nominal = $rate['nominal'] ?? $commonRate;
            if ($rate === null && $other !== null && ! in_array($code, $decisions['tarif_umum'], true)) {
                $blocking[] = ['baris' => $row['baris'], 'kode' => $code,
                    'pesan' => 'Bayar Rp'.number_format($other, 0, ',', '.')." ({$counts[$other]} bulan) lebih sering dari tarif umum ($common bulan). Tetapkan tarif khusus atau tarif_umum."];
            }
            foreach ($row['iuran'] as $m => $amount) {
                if ($amount > 0 && $amount !== $nominal) {
                    $notes[] = ['kode' => $code, 'periode' => sprintf('%04d-%02d', $year, $m + 1),
                        'jenis' => $amount < $nominal ? 'kurang' : 'lebih', 'nominal' => $amount, 'tarif' => $nominal];
                }
            }
            $houses[$code] = ['kode' => $code, 'alamat_key' => $key, 'blok' => $row['blok'], 'jalan' => $street, 'nomor' => $row['nomor'],
                'lewati' => false, 'tarif' => $rate === null ? ['mode' => 'umum', 'nominal' => $commonRate] : ['mode' => 'khusus'] + $rate,
                'iuran' => $row['iuran'], 'sumbangan' => $row['sumbangan']];
        }
        $known = array_keys($houses);
        foreach (array_merge(array_keys($decisions['tarif']), $decisions['tarif_umum'], $decisions['tidak_ditagih']) as $code) {
            if (! in_array($code, $known, true)) {
                $blocking[] = ['baris' => null, 'kode' => $code, 'pesan' => 'Kode di berkas keputusan tidak ada di sheet.'];
            }
        }
        ksort($houses, SORT_STRING);
        $houses = array_values($houses);

        return ['tahun' => $year, 'sampai' => sprintf('%04d-%02d', $year, $untilMonth), 'tarif_umum' => $commonRate,
            'rumah' => $houses, 'perlu_keputusan' => $blocking, 'catatan' => $notes, 'ringkasan' => self::summary($houses, $untilMonth),
            'sidik_jari' => hash('sha256', json_encode([$year, $untilMonth, $commonRate, $houses], JSON_THROW_ON_ERROR))];
    }

    private static function untilMonth(int $year, string $until): int
    {
        $period = Dues::period($until);
        if ((int) $period->format('Y') !== $year) {
            throw new DomainConflict('IMPORT_PERIOD_INVALID', 'Batas impor harus di tahun yang sama.', 422);
        }

        return (int) $period->format('n');
    }

    /** Angka per bulan: tagihan, dilunasi buku lama, sisa, dan kelebihan (uang sudah di saldo awal kas). */
    public static function months(array $house, int $untilMonth): array
    {
        $result = [];
        foreach ($house['iuran'] as $m => $amount) {
            if ($m + 1 > $untilMonth && $amount === 0) {
                continue;
            }
            $due = $house['tarif']['nominal'];
            $settled = min($amount, $due);
            $result[$m + 1] = ['tagihan' => $due, 'buku_lama' => $settled, 'sisa' => $due - $settled, 'lebih' => max(0, $amount - $due)];
        }

        return $result;
    }

    private static function summary(array $houses, int $untilMonth): array
    {
        $s = ['rumah' => 0, 'rumah_dilewati' => 0, 'tarif_khusus' => 0, 'tagihan' => 0, 'bulan_lunas' => 0, 'bulan_kurang' => 0,
            'bulan_belum' => 0, 'bulan_di_muka' => 0, 'nominal_tagihan' => 0, 'nominal_buku_lama' => 0, 'nominal_sisa' => 0, 'nominal_lebih' => 0,
            'sumbangan_kolom' => 0];
        foreach ($houses as $house) {
            if ($house['lewati']) {
                $s['rumah_dilewati']++;

                continue;
            }
            $s['rumah']++;
            $s['tarif_khusus'] += $house['tarif']['mode'] === 'khusus' ? 1 : 0;
            $s['sumbangan_kolom'] += $house['sumbangan'];
            foreach (self::months($house, $untilMonth) as $m => $month) {
                $s['tagihan']++;
                $s['nominal_tagihan'] += $month['tagihan'];
                $s['nominal_buku_lama'] += $month['buku_lama'];
                $s['nominal_sisa'] += $month['sisa'];
                $s['nominal_lebih'] += $month['lebih'];
                $s[$month['buku_lama'] === 0 ? 'bulan_belum' : ($month['sisa'] > 0 ? 'bulan_kurang' : 'bulan_lunas')]++;
                $s['bulan_di_muka'] += $m > $untilMonth ? 1 : 0;
            }
        }

        return $s;
    }

    /**
     * Terapkan rencana. Semua atau tidak sama sekali; invarian M18 divalidasi saat commit.
     */
    public static function apply(Akun $actor, array $plan): array
    {
        if (! config('finance.v2')) {
            throw new DomainConflict('FINANCE_V2_REQUIRED', 'Aktifkan keuangan v2 sebelum impor buku lama.', 422);
        }
        if (! $actor->isPengurus() || ! $actor->isAktif()) {
            throw new DomainConflict('IMPORT_ACTOR_INVALID', 'Impor hanya oleh akun pengurus aktif.', 403);
        }
        if ($plan['perlu_keputusan'] !== []) {
            throw new DomainConflict('IMPORT_DECISIONS_REQUIRED', 'Masih ada '.count($plan['perlu_keputusan']).' hal yang perlu keputusan.', 422);
        }
        $year = (int) $plan['tahun'];
        $untilMonth = self::untilMonth($year, $plan['sampai']);
        if ($issue = self::periodIssue($plan['sampai'])) {
            throw new DomainConflict('IMPORT_PERIOD_INVALID', $issue, 422);
        }
        $january = sprintf('%04d-01-01', $year);

        return DB::transaction(function () use ($actor, $plan, $year, $untilMonth, $january) {
            FlexibleDues::lock();
            if (DB::table('impor_keuangan')->where('tahun', $year)->exists()) {
                throw new DomainConflict('IMPORT_ALREADY_APPLIED', "Impor buku lama tahun $year sudah pernah diterapkan.");
            }
            if (self::commonRate($year) !== (int) $plan['tarif_umum']) {
                throw new DomainConflict('FINANCE_CHANGED', 'Tarif umum berubah sejak rencana dibuat. Jalankan cek ulang.');
            }
            if ($issue = self::rateIssue($year)) {
                throw new DomainConflict('IMPORT_RATE_CHANGED', $issue, 422);
            }
            $houses = array_values(array_filter($plan['rumah'], fn ($h) => ! $h['lewati']));
            self::ensureCommonRate($actor, $year, $houses);

            $ids = [];
            $created = 0;
            foreach ($houses as $house) {
                [$ids[$house['kode']], $new] = self::house($actor, $house, $january);
                $created += $new ? 1 : 0;
                if ($house['tarif']['mode'] === 'khusus') {
                    DB::table('tarif_rumah')->insert(['id' => (string) Str::uuid(), 'rumah_id' => $ids[$house['kode']], 'mode' => 'khusus',
                        'nominal' => $house['tarif']['nominal'], 'mulai_periode' => $january, 'alasan' => $house['tarif']['alasan'],
                        'dibuat_oleh_id' => $actor->id]);
                }
            }

            $months = array_map(fn ($h) => self::months($h, $untilMonth), $houses);
            for ($m = 1; $m <= 12; $m++) {
                $need = [];
                foreach ($houses as $i => $house) {
                    if (isset($months[$i][$m])) {
                        $need[] = $ids[$house['kode']];
                    }
                }
                if ($need !== []) {
                    Dues::generateForHouses(CarbonImmutable::create($year, $m, 1, 0, 0, 0, 'UTC'), $need, $actor);
                }
            }

            $batch = (string) Str::uuid();
            DB::table('impor_keuangan')->insert(['id' => $batch, 'tahun' => $year, 'sampai_periode' => sprintf('%04d-%02d-01', $year, $untilMonth),
                'sidik_jari' => $plan['sidik_jari'], 'ringkasan' => json_encode($plan['ringkasan'], JSON_THROW_ON_ERROR), 'diterapkan_oleh_id' => $actor->id]);
            // Satu query baca + insert per kelompok: impor ±2.000 tagihan tetap singkat walau database jauh (Neon).
            $charges = DB::table('rt05_tagihan_saldo')->whereIn('rumah_id', array_values($ids))
                ->whereBetween('periode', [$january, sprintf('%04d-12-01', $year)])->get()->keyBy(fn ($c) => $c->rumah_id.'|'.substr($c->periode, 0, 10));
            $corrected = DB::table('penyesuaian_tagihan')->whereIn('tagihan_id', $charges->pluck('id')->all())->pluck('tagihan_id')->flip();
            $adjustments = $settlements = $touched = [];
            foreach ($houses as $i => $house) {
                foreach ($months[$i] as $m => $month) {
                    $period = sprintf('%04d-%02d-01', $year, $m);
                    $charge = $charges->get($ids[$house['kode']].'|'.$period);
                    if (! $charge) {
                        throw new DomainConflict('IMPORT_CONFLICT', "Tagihan {$house['kode']} ".substr($period, 0, 7).' tidak dapat dibuat.');
                    }
                    if ((int) $charge->terbayar_uang + (int) $charge->terbayar_titipan + (int) $charge->keringanan + (int) $charge->terbayar_buku_lama > 0
                        || isset($corrected[$charge->id])) {
                        throw new DomainConflict('IMPORT_CONFLICT', "Tagihan {$house['kode']} ".substr($period, 0, 7).' sudah punya pelunasan atau koreksi di aplikasi.');
                    }
                    if ((int) $charge->nominal_efektif !== $month['tagihan']) {
                        $adjustments[] = ['id' => (string) Str::uuid(), 'tagihan_id' => $charge->id,
                            'delta' => $month['tagihan'] - (int) $charge->nominal_efektif, 'dicatat_oleh_id' => $actor->id,
                            'alasan' => "Tarif sesuai rekap bendahara $year (impor buku lama)"];
                    }
                    if ($month['buku_lama'] > 0) {
                        $settlements[] = ['id' => (string) Str::uuid(), 'tagihan_id' => $charge->id, 'impor_id' => $batch,
                            'nominal' => $month['buku_lama'], 'dicatat_oleh_id' => $actor->id];
                    }
                    $touched[] = $charge->id;
                }
            }
            foreach (array_chunk($adjustments, 500) as $chunk) {
                DB::table('penyesuaian_tagihan')->insert($chunk);
            }
            foreach (array_chunk($settlements, 500) as $chunk) {
                DB::table('pelunasan_buku_lama')->insert($chunk);
            }
            foreach (array_chunk($touched, 500) as $chunk) {
                DB::update("UPDATE tagihan_iuran t SET status = CASE WHEN s.sisa = 0 THEN 'lunas' ELSE 'belum_bayar' END
                    FROM rt05_tagihan_saldo s WHERE s.id = t.id AND t.id IN (".implode(',', array_fill(0, count($chunk), '?')).')
                    AND t.status IS DISTINCT FROM (CASE WHEN s.sisa = 0 THEN \'lunas\' ELSE \'belum_bayar\' END)', $chunk);
            }
            $summary = $plan['ringkasan'] + ['rumah_baru' => $created];
            Audit::record($actor, 'keuangan.impor_buku_lama', 'impor_keuangan', $batch, $summary);

            return ['id' => $batch] + $summary;
        });
    }

    /**
     * Tarif umum Januari diperlukan agar tagihan Jan–Sep dapat dibuat. Rumah di luar impor yang mulai tagihnya lebih awal dari
     * tarif pertama akan ikut tertagih mundur oleh generator, jadi impor ditolak sampai pengurus memeriksanya.
     */
    private static function ensureCommonRate(Akun $actor, int $year, array $houses): void
    {
        $january = sprintf('%04d-01-01', $year);
        if (DB::table('tarif_iuran')->whereNull('dibatalkan_at')->where('mulai_periode', '<=', $january)->exists()) {
            return;
        }
        $first = (string) DB::table('tarif_iuran')->whereNull('dibatalkan_at')->min('mulai_periode');
        $keys = array_column($houses, 'alamat_key');
        $others = Rumah::whereNull('archived_at')->where('tagihan_aktif', true)->whereNotNull('mulai_tagih')
            ->where('mulai_tagih', '<', $first ?: $january)->whereNotIn('alamat_key', $keys)->orderBy('kode_rumah')->pluck('kode_rumah');
        if ($others->isNotEmpty()) {
            throw new DomainConflict('IMPORT_BACKFILL_RISK', 'Rumah di luar rekap akan ikut tertagih mundur: '.$others->implode(', ').'. Periksa mulai tagihnya dulu.');
        }
        DB::table('tarif_iuran')->insert(['id' => (string) Str::uuid(), 'nominal' => 75000, 'mulai_periode' => $january,
            'alasan' => "Tarif umum Rp75.000 sesuai rekap bendahara $year", 'dibuat_oleh_id' => $actor->id]);
    }

    /** @return array{0:string, 1:bool} id rumah dan apakah baru dibuat */
    private static function house(Akun $actor, array $house, string $january): array
    {
        $existing = Rumah::where('alamat_key', $house['alamat_key'])->lockForUpdate()->first();
        if ($existing) {
            if ($existing->archived_at !== null || ! $existing->tagihan_aktif) {
                throw new DomainConflict('IMPORT_CONFLICT', "Rumah {$house['kode']} diarsipkan atau tagihannya nonaktif; selesaikan dulu di aplikasi.");
            }
            if (DB::table('tarif_rumah')->where('rumah_id', $existing->id)->whereNull('dibatalkan_at')->exists()) {
                throw new DomainConflict('IMPORT_CONFLICT', "Rumah {$house['kode']} sudah punya tarif khusus di aplikasi; selesaikan manual.");
            }
            if ($existing->mulai_tagih === null || $existing->mulai_tagih->toDateString() > $january) {
                $before = $existing->mulai_tagih?->format('Y-m');
                $existing->mulai_tagih = $january;
                $existing->versi++;
                $existing->save();
                Audit::record($actor, 'rumah.mulai_tagih_impor', 'rumah', $existing->id, ['sebelum' => $before, 'sesudah' => substr($january, 0, 7)]);
            }

            return [$existing->id, false];
        }
        if (Rumah::where('kode_rumah', $house['kode'])->exists()) {
            throw new DomainConflict('IMPORT_CONFLICT', "Kode rumah {$house['kode']} sudah dipakai alamat lain.");
        }
        $created = Rumah::create(['kode_rumah' => $house['kode'], 'blok' => $house['blok'], 'jalan' => $house['jalan'], 'nomor' => $house['nomor'],
            'alamat_key' => $house['alamat_key'], 'tagihan_aktif' => true, 'mulai_tagih' => $january]);
        Audit::record($actor, 'rumah.dibuat', 'rumah', $created->id, ['sumber' => 'impor_buku_lama']);

        return [$created->id, true];
    }

    /** Batalkan satu pelunasan buku lama yang salah; tagihan kembali terbuka. */
    public static function cancel(Akun $actor, string $id, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 300) {
            throw new DomainConflict('REASON_REQUIRED', 'Alasan wajib diisi 5–300 karakter.', 422);
        }
        DB::transaction(function () use ($actor, $id, $reason) {
            FlexibleDues::lock();
            $entry = DB::table('pelunasan_buku_lama')->where('id', $id)->whereNull('dibatalkan_at')->first();
            if (! $entry) {
                throw new DomainConflict('OLD_BOOK_NOT_FOUND', 'Catatan buku lama tidak ditemukan atau sudah dibatalkan.', 404);
            }
            DB::table('pelunasan_buku_lama')->where('id', $id)->update(['dibatalkan_at' => now(), 'dibatalkan_oleh_id' => $actor->id, 'alasan_pembatalan' => $reason]);
            FlexibleDues::sync([$entry->tagihan_id]);
            Audit::record($actor, 'buku_lama.dibatalkan', 'pelunasan_buku_lama', $id, ['alasan' => $reason]);
        });
    }
}
