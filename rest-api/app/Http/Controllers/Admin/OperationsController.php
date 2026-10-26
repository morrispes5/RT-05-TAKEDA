<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\FlexibleDues;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\Akun;
use App\Models\Aspirasi;
use App\Models\AuditLog;
use App\Models\PembayaranIuran;
use App\Models\Pengaduan;
use App\Models\PermohonanAkun;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Models\TransaksiKas;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Dashboard pengurus, audit, dan ekspor CSV (FR18, FR19). */
class OperationsController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $period = CarbonImmutable::now('Asia/Jakarta')->startOfMonth()->toDateString();

        return Api::data([
            'rumah_aktif' => Rumah::whereNull('archived_at')->count(),
            'akun_aktif' => Akun::where('status', 'aktif')->count(),
            'permohonan_menunggu' => PermohonanAkun::where('status', 'diajukan')->count(),
            'pengaduan' => Pengaduan::whereNull('archived_at')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'aspirasi' => Aspirasi::whereNull('archived_at')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'iuran_bulan_ini' => [
                'periode' => substr($period, 0, 7),
                'lunas' => TagihanIuran::where('periode', $period)->where('status', 'lunas')->count(),
                'belum_bayar' => TagihanIuran::where('periode', $period)->where('status', 'belum_bayar')->count(),
            ],
            'saldo_kas' => Dues::balance(),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $q = AuditLog::query()
            ->when($request->query('entity_type'), fn ($q, $t) => $q->where('entity_type', $t))
            ->orderByDesc('created_at');
        $names = Akun::pluck('nama', 'id');

        return Api::paginate($request, $q, fn (AuditLog $l) => [
            'id' => $l->id, 'aksi' => $l->action, 'entitas' => $l->entity_type, 'entitas_id' => $l->entity_id,
            'aktor' => $l->actor_id ? $names[$l->actor_id] ?? null : 'Sistem', 'detail' => $l->safe_diff,
            'created_at' => Present::ts($l->created_at),
        ]);
    }

    /** GET /admin/exports/{jenis}.csv — CSV kecil sinkron; nilai diawali = + - @ tab CR diberi apostrof. */
    public function export(Request $request, string $jenis): StreamedResponse
    {
        abort_unless(in_array($jenis, ['iuran', 'kas', 'rumah', 'pembayaran'], true), 404);
        // UAT M15: rekap bulan tertentu dari aplikasi (?periode=YYYY-MM) untuk iuran, kas, dan pembayaran. Kosong = semua periode.
        $periode = $request->validate(['periode' => ['nullable', 'date_format:Y-m']])['periode'] ?? null;
        Audit::record($request->user(), 'ekspor.csv', $jenis, null, $periode ? ['periode' => $periode] : []);
        // Audit M16: kas dan pembayaran disaring per tanggal transaksi/bayar di bulan itu (sebelumnya semua periode).
        $bulan = $periode ? [CarbonImmutable::parse($periode.'-01')->toDateString(), CarbonImmutable::parse($periode.'-01')->endOfMonth()->toDateString()] : null;

        if (config('finance.v2') && $jenis !== 'rumah') {
            return $this->financeCsv($jenis, $periode);
        }

        [$header, $rows] = match ($jenis) {
            'iuran' => [['kode_rumah', 'periode', 'nominal', 'status'], TagihanIuran::join('rumah', 'rumah.id', '=', 'tagihan_iuran.rumah_id')
                ->when($periode, fn ($q) => $q->where('tagihan_iuran.periode', $periode.'-01'))
                ->orderBy('periode')->orderBy('rumah.kode_rumah')
                ->get(['rumah.kode_rumah', 'tagihan_iuran.periode', 'tagihan_iuran.nominal_tagihan', 'tagihan_iuran.status'])
                ->map(fn ($r) => [$r->kode_rumah, substr($r->periode, 0, 7), $r->nominal_tagihan, $r->status])],
            'kas' => [['tanggal', 'jenis', 'sumber', 'kategori', 'nominal', 'keterangan'], TransaksiKas::when($bulan, fn ($q) => $q->whereBetween('tanggal', $bulan))->orderBy('tanggal')->get()
                ->map(fn ($k) => [$k->tanggal->toDateString(), $k->jenis, $k->sumber, $k->kategori, $k->nominal, $k->keterangan])],
            'rumah' => [['kode_rumah', 'blok', 'jalan', 'nomor', 'tagihan_aktif'], Rumah::whereNull('archived_at')->orderBy('kode_rumah')->get()
                ->map(fn ($r) => [$r->kode_rumah, $r->blok, $r->jalan, $r->nomor, $r->tagihan_aktif ? 'ya' : 'tidak'])],
            // UAT M15: daftar pembayaran offline (termasuk yang dibatalkan beserta alasannya).
            'pembayaran' => [['nomor_bukti', 'kode_rumah', 'tanggal_bayar', 'periode', 'total_bayar', 'status', 'catatan', 'alasan_pembatalan'],
                PembayaranIuran::with(['rumah', 'alokasi.tagihan'])->when($bulan, fn ($q) => $q->whereBetween('tanggal_bayar', $bulan))->orderBy('tanggal_bayar')->orderBy('nomor_bukti')->get()
                    ->map(fn ($p) => [$p->nomor_bukti, $p->rumah?->kode_rumah, $p->tanggal_bayar?->toDateString(), $p->alokasi
                        ->map(fn ($a) => substr((string) $a->tagihan?->periode, 0, 7))->filter()->unique()->sort()->implode(' '),
                        $p->total_bayar, $p->status, $p->catatan, $p->alasan_pembatalan])],
        };

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safeCell'], $row));
            }
            fclose($out);
        }, "rt05-$jenis-".now('Asia/Jakarta')->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public static function safeCell(mixed $v): string
    {
        $s = (string) $v;

        return preg_match('/^[=+\-@\t\r]/', $s) && ! is_numeric($s) ? "'".$s : $s;
    }

    private function financeCsv(string $jenis, ?string $period): StreamedResponse
    {
        [$header, $rows] = DB::transaction(function () use ($jenis, $period) {
            FlexibleDues::lock();
            $start = $period ? Dues::period($period)->toDateString() : null;
            $end = $period ? Dues::period($period)->addMonth()->toDateString() : null;
            if ($jenis === 'iuran') {
                return [['kode_rumah', 'periode', 'nominal_snapshot', 'nominal_efektif', 'terbayar', 'dari_titipan', 'keringanan', 'sisa', 'status'],
                    array_map(fn ($r) => [$r['kode_rumah'], $r['periode'], $r['nominal_snapshot'], $r['nominal_efektif'], $r['terbayar'], $r['terbayar_titipan'], $r['keringanan'], $r['sisa'], $r['status_label']], FinanceReports::charges($start, $end))];
            }
            if ($jenis === 'kas') {
                return [['tanggal', 'jenis', 'sumber', 'kategori', 'nominal', 'keterangan', 'nomor_bukti', 'bagian_iuran', 'sumbangan', 'titipan'],
                    array_map(fn ($r) => [$r['tanggal'], $r['jenis'], $r['sumber'], $r['kategori'], $r['nominal'], $r['keterangan'], $r['nomor_bukti'], $r['iuran'], $r['sumbangan'], $r['titipan']], FinanceReports::cash($start, $end))];
            }

            return [['nomor_bukti', 'kode_rumah', 'tanggal_bayar', 'periode', 'total_bayar', 'bagian_iuran_asal', 'sumbangan', 'titipan', 'status', 'catatan', 'alasan_pembatalan'],
                PembayaranIuran::with(['rumah', 'alokasi.tagihan', 'keringanan.tagihan'])
                    ->when($start, fn ($q) => $q->where('tanggal_bayar', '>=', $start))->when($end, fn ($q) => $q->where('tanggal_bayar', '<', $end))
                    ->orderBy('tanggal_bayar')->orderBy('nomor_bukti')->get()->map(fn ($p) => [
                        $p->nomor_bukti, $p->rumah->kode_rumah, $p->tanggal_bayar->toDateString(),
                        $p->alokasi->concat($p->keringanan)->map(fn ($r) => $r->tagihan->periode->format('Y-m'))->unique()->sort()->implode(' '),
                        $p->total_bayar, $p->total_bayar - $p->nominal_sumbangan - $p->nominal_titipan, $p->nominal_sumbangan, $p->nominal_titipan,
                        $p->status, $p->catatan, $p->alasan_pembatalan,
                    ])->all()];
        });

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '');
            }
            fclose($out);
        }, "rt05-$jenis-".($period ?? 'semua').'.csv', ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }
}
