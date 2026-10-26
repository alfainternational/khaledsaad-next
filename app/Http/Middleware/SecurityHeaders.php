<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = bin2hex(random_bytes(16));
        view()->share('cspNonce', $nonce);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // 'unsafe-eval' is required by the standard Alpine.js build (x-data / @click expressions
        // are compiled with new Function). Inline injection stays blocked by the nonce.
        $scriptSrc = "'self' 'nonce-{$nonce}' 'unsafe-eval'";
        $connectSrc = "'self'";
        if (app()->isLocal()) {
            $scriptSrc .= ' http://localhost:5173 http://127.0.0.1:5173';
            $connectSrc .= ' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173';
        }
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src {$scriptSrc}; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src {$connectSrc}; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

        return $response;
    }
}
