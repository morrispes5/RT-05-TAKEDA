<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit 10 Nov 2026 (ADR60 Fase 3): respons API production tanpa HSTS dan masih membawa `X-Powered-By` (versi PHP).
 * Header dipasang aplikasi sendiri agar sama di VPS maupun shared hosting. HSTS hanya lewat HTTPS dan tanpa
 * includeSubDomains, sama dengan website (subdomain lain milik pemilik tidak ikut terkunci).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $defaults = ['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer'];
        if ($request->isSecure()) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000';
        }
        // Respons yang sudah menetapkan header sendiri (mis. halaman reset kata sandi) tidak digandakan.
        foreach ($defaults as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }
        $headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
