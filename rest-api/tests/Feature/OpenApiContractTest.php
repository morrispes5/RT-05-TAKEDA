<?php

namespace Tests\Feature;

use App\Http\Responses\ApiError;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\Support\OpenApi;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    public function test_spec_is_openapi_31_and_every_operation_declares_status_and_milestone(): void
    {
        $this->assertMatchesRegularExpression('/^3\.1\.\d+$/', OpenApi::spec()['openapi']);

        foreach (OpenApi::operations() as $operation => $meta) {
            $this->assertContains($meta['status'], ['implemented', 'planned'], $operation);
            $this->assertMatchesRegularExpression('/^M(0[2-9]|1[0-6])$/', $meta['milestone'], $operation);
        }
    }

    public function test_implemented_operations_match_registered_routes_exactly(): void
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes[] = $method.' /'.ltrim($route->uri(), '/');
            }
        }

        $implemented = array_keys(array_filter(OpenApi::operations(), fn ($meta) => $meta['status'] === 'implemented'));

        sort($routes);
        sort($implemented);
        $this->assertSame($implemented, $routes, 'Setiap route harus implemented di OpenAPI dan sebaliknya.');
    }

    public function test_planned_operations_are_not_served(): void
    {
        $planned = array_keys(array_filter(OpenApi::operations(), fn ($meta) => $meta['status'] === 'planned'));
        $this->assertGreaterThan(10, count($planned));

        foreach (['GET /api/v1/public/articles', 'POST /api/v1/media', 'GET /api/v1/app-version'] as $operation) {
            $this->assertContains($operation, $planned);
            [$method, $path] = explode(' ', $operation);
            $this->json($method, $path)->assertNotFound();
        }
    }

    public function test_implemented_responses_validate_against_schemas(): void
    {
        $cases = [
            ['/health/live', 200, 'HealthLive'],
            ['/health/ready', 200, 'HealthReady'],
            ['/api/v1', 200, 'ServiceInfoEnvelope'],
            ['/api/v1/tidak-ada', 404, 'ErrorEnvelope'],
        ];

        foreach ($cases as [$path, $status, $schema]) {
            $response = $this->getJson($path)->assertStatus($status);
            $this->assertSame([], OpenApi::errors($response->json(), $schema), "$path vs $schema");
            $this->assertTrue($response->headers->has('X-Request-Id'), $path);
        }
    }

    public function test_every_error_code_the_api_can_emit_matches_the_contract(): void
    {
        $schema = OpenApi::spec()['components']['schemas']['ErrorCode'];
        $codes = array_column((new ReflectionClass(ApiError::class))->getConstant('BY_STATUS'), 0);
        $codes[] = 'INTERNAL_ERROR';

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/'.$schema['pattern'].'/', $code);
        }
        $this->assertSame([], array_values(array_diff($codes, $schema['x-known-values'])));
    }
}
