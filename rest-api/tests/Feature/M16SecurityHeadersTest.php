<?php

namespace Tests\Feature;

use Tests\TestCase;

/** ADR60 Fase 3 (audit 10 Nov 2026): respons API production tanpa HSTS dan membawa X-Powered-By. */
class M16SecurityHeadersTest extends TestCase
{
    public function test_api_responses_over_https_carry_security_headers(): void
    {
        $response = $this->getJson('https://localhost/api/v1/')->assertOk();

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_hsts_is_only_sent_over_https_and_errors_get_the_same_headers(): void
    {
        $this->getJson('http://localhost/api/v1/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
        $this->getJson('https://localhost/api/v1/dues/my')->assertStatus(401)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
