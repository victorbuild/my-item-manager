<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Csp\AddCspHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP Headers Middleware
 *
 * 全域套用 CSP，但排除 /docs 路由（Scribe 文件使用多個外部 CDN）。
 */
class CspHeaders
{
    public function __construct(private readonly AddCspHeaders $csp)
    {
    }

    /**
     * 套用 CSP headers，/docs 路由略過
     *
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('docs', 'docs/*')) {
            return $next($request);
        }

        return $this->csp->handle($request, $next);
    }
}
