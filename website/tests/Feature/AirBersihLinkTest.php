<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ADR40: Area Pengurus menautkan aplikasi Air Bersih KSM (repo terpisah) di tab baru. Sementara hanya tautan;
 * tidak ada data air bersih di database RT05 sampai fitur itu digabung.
 */
class AirBersihLinkTest extends TestCase
{
    public function test_pengurus_sidebar_links_air_bersih_in_new_tab(): void
    {
        foreach (['/pengurus', '/pengurus/artikel', '/pengurus/profil'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<a href="https://airbersih-ksm-claude.vercel.app" target="_blank" rel="noopener" data-air-bersih-ksm>', false)
                ->assertSee('Tagihan air KSM');
        }
    }

    public function test_url_follows_config(): void
    {
        config(['services.air_bersih_ksm.url' => 'https://air.example.test']);
        $this->get('/pengurus')->assertOk()->assertSee('href="https://air.example.test"', false);
    }

    public function test_public_pages_do_not_show_the_link(): void
    {
        foreach (['/', '/profil', '/artikel'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-air-bersih-ksm', false);
        }
    }
}
