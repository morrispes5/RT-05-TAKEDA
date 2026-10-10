<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Dues;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\Akun;
use App\Models\Aspirasi;
use App\Models\AuditLog;
use App\Models\Pengaduan;
use App\Models\PermohonanAkun;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use App\Models\TransaksiKas;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        abort_unless(in_array($jenis, ['iuran', 'kas', 'rumah'], true), 404);
        Audit::record($request->user(), 'ekspor.csv', $jenis, null);

        [$header, $rows] = match ($jenis) {
            'iuran' => [['kode_rumah', 'periode', 'nominal', 'status'], TagihanIuran::join('rumah', 'rumah.id', '=', 'tagihan_iuran.rumah_id')
                ->orderBy('periode')->orderBy('rumah.kode_rumah')
                ->get(['rumah.kode_rumah', 'tagihan_iuran.periode', 'tagihan_iuran.nominal_tagihan', 'tagihan_iuran.status'])
                ->map(fn ($r) => [$r->kode_rumah, substr($r->periode, 0, 7), $r->nominal_tagihan, $r->status])],
            'kas' => [['tanggal', 'jenis', 'sumber', 'kategori', 'nominal', 'keterangan'], TransaksiKas::orderBy('tanggal')->get()
                ->map(fn ($k) => [$k->tanggal->toDateString(), $k->jenis, $k->sumber, $k->kategori, $k->nominal, $k->keterangan])],
            'rumah' => [['kode_rumah', 'blok', 'jalan', 'nomor', 'tagihan_aktif'], Rumah::whereNull('archived_at')->orderBy('kode_rumah')->get()
                ->map(fn ($r) => [$r->kode_rumah, $r->blok, $r->jalan, $r->nomor, $r->tagihan_aktif ? 'ya' : 'tidak'])],
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
}
