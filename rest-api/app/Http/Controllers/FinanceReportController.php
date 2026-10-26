<?php

namespace App\Http\Controllers;

use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\FinanceWorkbook;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Responses\Api;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        FlexibleFinanceController::enabled();
        $data = $request->validate(['tahun' => ['nullable', 'integer', 'between:2000,2100', 'prohibits:periode'],
            'periode' => ['nullable', 'date_format:Y-m', 'prohibits:tahun']]);
        $year = (int) ($data['tahun'] ?? substr($data['periode'] ?? now('Asia/Jakarta')->format('Y-m'), 0, 4));
        abort_unless($year >= 2000 && $year <= 2100, 422);
        $report = FinanceReports::year($year);
        if (isset($data['periode'])) {
            $report = $report['bulan'][(int) substr($data['periode'], 5, 2) - 1] + [
                'tahun' => $year, 'posisi_pelunasan' => $report['posisi_pelunasan'], 'catatan' => $report['catatan']];
        }

        return Api::data($report)->header('Cache-Control', 'private, no-store');
    }

    private function year(Request $request): int
    {
        FlexibleFinanceController::enabled();

        return (int) $request->validate(['tahun' => ['required', 'integer', 'between:2000,2100']])['tahun'];
    }

    public function csv(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $report = FinanceReports::year($year);
        Audit::record($request->user(), 'ekspor.csv', 'keuangan', null, ['tahun' => $year]);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, FinanceReports::SUMMARY_HEADER, ',', '"', '');
            foreach (FinanceReports::summaryRows($report) as $row) {
                fputcsv($out, array_map([OperationsController::class, 'safeCell'], $row), ',', '"', '');
            }
            fclose($out);
        }, "rt05-keuangan-$year.csv", ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }

    public function xlsx(Request $request): BinaryFileResponse
    {
        $year = $this->year($request);
        $report = FinanceReports::year($year);
        $directory = storage_path('app/private/finance');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Folder ekspor privat tidak tersedia.');
        }
        $file = $directory.'/'.Str::uuid().'.xlsx';
        try {
            FinanceWorkbook::write($report, $file);
            Audit::record($request->user(), 'ekspor.xlsx', 'keuangan', null, ['tahun' => $year]);
            clearstatcache(true, $file);

            return response()->download($file, "rt05-keuangan-$year.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (is_file($file)) {
                unlink($file);
            }
            throw $e;
        }
    }
}
