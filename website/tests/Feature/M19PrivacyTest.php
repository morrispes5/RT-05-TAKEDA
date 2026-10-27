<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Tests\TestCase;

class M19PrivacyTest extends TestCase
{
    public function test_emergency_privacy_is_opt_in_and_discloses_identity_only_for_confirmed_emergencies(): void
    {
        config(['privacy.panic_enabled' => false]);
        $this->assertSame('2026-10-26', SiteContent::privacy()['versi']);
        config(['privacy.panic_enabled' => true]);
        $this->assertSame('2026-10-27', SiteContent::privacy()['versi']);
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('Versi 27 Oktober 2026')
            ->assertSee('nama pelapor, rumah, dan jenis darurat')->assertSee('Laporan darurat tidak anonim.')
            ->assertSee('Tidak ada pengambilan lokasi GPS.')->assertSee('Teks layar kunci tidak memuat nama atau alamat');
        // The new section is byte-identical to the text shipped in the APK.
        $dart = file_get_contents(base_path('../mobile/lib/features/auth/privacy_policy.dart'));
        $section = array_values(array_filter(SiteContent::privacy()['bagian'], fn ($s) => $s['judul'] === 'Tombol panik'))[0];
        foreach ($section['butir'] as $text) {
            $this->assertStringContainsString($text, $dart);
        }
    }
}
