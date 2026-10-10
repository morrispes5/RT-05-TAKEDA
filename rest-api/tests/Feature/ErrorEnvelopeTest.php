<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\OpenApi;
use Tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Route khusus tes untuk memicu tiap kelas error melalui pipeline HTTP nyata.
        Route::middleware('api')->prefix('api/v1/__test')->group(function () {
            Route::post('validate', fn (Request $r) => $r->validate(['periods' => ['required', 'array']]));
            Route::get('unauthenticated', fn () => throw new AuthenticationException);
            Route::get('forbidden', fn () => throw new AuthorizationException('rahasia internal'));
            Route::get('csrf', fn () => throw new TokenMismatchException);
            Route::get('boom', fn () => throw new RuntimeException('SQLSTATE[08006] host=ep-rahasia.neon.tech password=xyz'));
            Route::get('throttled', fn () => ['ok' => true])->middleware('throttle:1,1');
        });
    }

    private function assertEnvelope($response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('error.code', $code);
        $this->assertSame([], OpenApi::errors($response->json(), 'ErrorEnvelope'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.request_id'));
    }

    public function test_validation_error_lists_fields(): void
    {
        $response = $this->postJson('/api/v1/__test/validate', []);

        $this->assertEnvelope($response, 422, 'VALIDATION_FAILED');
        $this->assertArrayHasKey('periods', $response->json('error.fields'));
    }

    public function test_auth_and_policy_errors(): void
    {
        $this->assertEnvelope($this->getJson('/api/v1/__test/unauthenticated'), 401, 'UNAUTHENTICATED');

        $forbidden = $this->getJson('/api/v1/__test/forbidden');
        $this->assertEnvelope($forbidden, 403, 'FORBIDDEN');
        $this->assertStringNotContainsString('rahasia', $forbidden->getContent());

        $this->assertEnvelope($this->getJson('/api/v1/__test/csrf'), 419, 'CSRF_EXPIRED');
    }

    public function test_not_found_and_method_not_allowed_are_json(): void
    {
        $this->assertEnvelope($this->getJson('/api/v1/tidak-ada'), 404, 'NOT_FOUND');
        $this->assertEnvelope($this->get('/halaman-html-tidak-ada'), 404, 'NOT_FOUND');
        $this->assertEnvelope($this->postJson('/health/live'), 405, 'METHOD_NOT_ALLOWED');
    }

    public function test_server_error_never_leaks_internals_even_in_debug(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/v1/__test/boom');

        $this->assertEnvelope($response, 500, 'INTERNAL_ERROR');
        foreach (['SQLSTATE', 'neon.tech', 'password', 'trace', 'RuntimeException', '.php'] as $leak) {
            $this->assertStringNotContainsString($leak, $response->getContent());
        }
    }

    public function test_rate_limit_returns_429_envelope(): void
    {
        $this->getJson('/api/v1/__test/throttled')->assertOk();

        $response = $this->getJson('/api/v1/__test/throttled');
        $this->assertEnvelope($response, 429, 'TOO_MANY_REQUESTS');
        $this->assertTrue($response->headers->has('Retry-After'));
    }
}
