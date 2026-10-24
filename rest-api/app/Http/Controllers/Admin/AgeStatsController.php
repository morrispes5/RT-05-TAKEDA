<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Residents\AgeGroups;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api;
use App\Models\Keluarga;
use App\Models\Warga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ADR42: statistik kelompok umur warga untuk pengurus (permintaan Pak RT 24 Okt 2026). Hanya anggota aktif dari keluarga yang
 * tidak diarsipkan. Daftar warga bisa disaring per kelompok, rentang umur bebas (mis. 60–65), atau yang belum mengisi tanggal
 * lahir. Data RT kecil, jadi dihitung di aplikasi tanpa kolom umur tersimpan.
 */
class AgeStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->validate([
            'kelompok' => ['sometimes', 'nullable', Rule::in(AgeGroups::codes())],
            'min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:130'],
            'max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:130', 'gte:min'],
            'belum_diisi' => ['sometimes', 'boolean'],
        ]);
        $today = AgeGroups::today();
        $families = Keluarga::whereNull('archived_at')
            ->with(['keanggotaanAktif.warga', 'penghunianAktif.rumah'])
            ->get();

        $rows = [];
        $houses = [];
        foreach ($families as $k) {
            $rumah = $k->penghunianAktif?->rumah;
            $summary = ['keluarga_id' => $k->id, 'versi_keluarga' => $k->versi,
                'rumah' => $rumah ? ['id' => $rumah->id, 'kode_rumah' => $rumah->kode_rumah, 'alamat' => $rumah->alamat()] : null,
                'jumlah_anggota' => 0, 'belum_diisi' => 0, 'kelompok' => array_fill_keys(AgeGroups::codes(), 0)];
            foreach ($k->keanggotaanAktif as $m) {
                $w = $m->warga;
                if (! $w instanceof Warga || $w->status !== 'aktif' || $w->archived_at !== null) {
                    continue;
                }
                $umur = AgeGroups::age($w->tanggal_lahir, $today);
                $kelompok = AgeGroups::group($umur);
                $summary['jumlah_anggota']++;
                $kelompok === null ? $summary['belum_diisi']++ : $summary['kelompok'][$kelompok]++;
                $rows[] = ['id' => $w->id, 'nama' => $w->nama, 'hubungan_keluarga' => $m->hubungan_keluarga, 'is_kepala' => $w->is_kepala,
                    'tanggal_lahir' => $w->tanggal_lahir?->format('Y-m-d'), 'umur' => $umur, 'kelompok_usia' => $kelompok,
                    'keluarga_id' => $k->id, 'versi_keluarga' => $k->versi, 'rumah' => $summary['rumah']];
            }
            if ($summary['jumlah_anggota'] > 0) {
                $houses[] = $summary;
            }
        }

        $counts = array_count_values(array_filter(array_column($rows, 'kelompok_usia')));
        $groups = array_map(fn (array $g) => $g + ['jumlah' => $counts[$g['kode']] ?? 0], AgeGroups::GROUPS);
        $filtered = array_values(array_filter($rows, function (array $r) use ($q) {
            if (! empty($q['belum_diisi'])) {
                return $r['umur'] === null;
            }
            if (! empty($q['kelompok']) && $r['kelompok_usia'] !== $q['kelompok']) {
                return false;
            }
            if (isset($q['min']) && ($r['umur'] === null || $r['umur'] < $q['min'])) {
                return false;
            }

            return ! (isset($q['max']) && ($r['umur'] === null || $r['umur'] > $q['max']));
        }));
        usort($filtered, fn (array $a, array $b) => [$b['umur'] ?? -1, $a['nama']] <=> [$a['umur'] ?? -1, $b['nama']]);
        usort($houses, fn (array $a, array $b) => ($a['rumah']['kode_rumah'] ?? '~') <=> ($b['rumah']['kode_rumah'] ?? '~'));

        return Api::data([
            'dihitung_pada' => $today->toDateString(),
            'total_warga' => count($rows),
            'belum_diisi' => count(array_filter($rows, fn (array $r) => $r['umur'] === null)),
            'kelompok' => $groups,
            'rumah' => $houses,
            'warga' => $filtered,
        ]);
    }
}
