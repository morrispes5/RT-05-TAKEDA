<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Audit 27 Okt 2026 (uji emulator 1024): Ringkasan pengurus menghitung "belum bayar" dari tagihan rumah yang sudah
 * diarsipkan (staging: 26 belum padahal rumah aktif 10 belum). Angka kerja harian hanya untuk rumah aktif.
 */
class AuditDashboardTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    public function test_dashboard_dues_counts_ignore_archived_houses(): void
    {
        $this->travelTo(now('Asia/Jakarta')->startOfMonth()->addDays(2));
        $admin = $this->pengurus('audit-dasbor@contoh.test');
        $aktif = $this->warga('ad1')->rumah();
        $arsip = $this->warga('ad2')->rumah();
        Dues::generate(Dues::period(now('Asia/Jakarta')->format('Y-m')));
        $before = $this->as($admin)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.iuran_bulan_ini.belum_bayar');

        DB::table('rumah')->where('id', $arsip->id)->update(['archived_at' => now(), 'tagihan_aktif' => false]);
        $after = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertSame($before - 1, $after['iuran_bulan_ini']['belum_bayar']);
        $this->assertTrue(DB::table('tagihan_iuran')->where('rumah_id', $arsip->id)->exists(), 'tagihan rumah arsip tetap ada di ledger');
        $this->assertTrue(DB::table('tagihan_iuran')->where('rumah_id', $aktif->id)->where('status', 'belum_bayar')->exists());
    }
}
