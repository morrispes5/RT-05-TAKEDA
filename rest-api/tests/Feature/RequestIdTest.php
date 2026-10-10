<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_generates_uuid_and_mirrors_it_in_meta(): void
    {
        $response = $this->getJson('/api/v1')->assertOk();

        $id = $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertSame($id, $response->json('meta.request_id'));
    }

    public function test_accepts_client_uuid(): void
    {
        $id = (string) Str::uuid();

        $this->getJson('/health/live', ['X-Request-Id' => strtoupper($id)])
            ->assertHeader('X-Request-Id', $id);
    }

    public function test_replaces_non_uuid_client_value(): void
    {
        $response = $this->getJson('/api/v1', ['X-Request-Id' => "abc\nINJECTED log line"]);

        $id = $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertStringNotContainsString('INJECTED', $response->getContent());
    }

    public function test_each_request_gets_a_distinct_id(): void
    {
        $a = $this->getJson('/health/live')->headers->get('X-Request-Id');
        $b = $this->getJson('/health/live')->headers->get('X-Request-Id');

        $this->assertNotSame($a, $b);
    }
}
