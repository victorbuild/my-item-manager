<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 設定 HTTP Security Headers
 *
 * 防禦 Clickjacking、MIME Sniffing、Referrer 洩漏、瀏覽器 API 濫用。
 * CSP 由 spatie/laravel-csp 獨立處理。
 */
class SecurityHeaders
{
    /**
     * 在每個 Response 加上安全相關 HTTP Headers
     *
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
