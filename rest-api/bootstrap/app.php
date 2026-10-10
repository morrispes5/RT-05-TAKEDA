<?php

use App\Exceptions\DomainConflict;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAkun;
use App\Http\Responses\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->alias(['akun' => EnsureAkun::class]);
        // Hanya gateway di jaringan privat Docker yang dipercaya untuk X-Forwarded-* (SECURITY.md).
        $proxies = array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')));
        if ($proxies !== []) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Aplikasi ini hanya API: setiap error menjadi JSON envelope, tanpa halaman HTML/stack trace.
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(fn (Throwable $e, Request $request) => ApiError::fromThrowable($e, $request));
        $exceptions->dontReportDuplicates();
        // Pelanggaran aturan bisnis yang wajar (nominal salah, versi basi) bukan error server.
        $exceptions->dontReport([DomainConflict::class]);
    })->create();
