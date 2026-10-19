<?php

namespace Tests\Feature;

use Tests\TestCase;

/** M15: header keamanan website (VPS dan shared hosting sama-sama lewat aplikasi). */
class SecurityHeadersTest extends TestCase
{
    public function test_pages_cannot_be_framed_and_hsts_is_only_sent_over_https(): void
    {
        foreach (['/', '/artikel', '/pengurus', '/pengurus/masuk'] as $path) {
            $this->get($path)->assertOk()
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
        $this->get('https://localhost/pengurus')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
