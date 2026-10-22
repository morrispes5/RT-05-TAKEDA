<?php

namespace App\Http\Middleware;

use App\Support\SiteContent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * M15: header keamanan website dari aplikasi sendiri, sehingga berlaku sama di VPS maupun shared
 * hosting. Halaman tidak boleh dibingkai situs lain (CMS /pengurus rawan clickjacking), HSTS hanya
 * lewat HTTPS dan tanpa includeSubDomains (subdomain lain milik pemilik tidak ikut terkunci).
 */
class SecurityHeaders
{
    private const CMS_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
        ."font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);
        // M16: hanya production yang boleh diindeks; area pengurus tidak pernah diindeks.
        if (! SiteContent::indexing() || $request->is('pengurus', 'pengurus/*')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow', false);
        }
        // Audit M16: CMS memegang sesi pengurus; batasi skrip/koneksi ke origin sendiri. Tidak dipasang di lingkungan local
        // karena aset Vite dev disajikan dari port lain.
        if ($request->is('pengurus', 'pengurus/*') && ! app()->environment('local')) {
            $headers->set('Content-Security-Policy', self::CMS_CSP, false);
        }
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
