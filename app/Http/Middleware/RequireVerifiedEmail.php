<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmail
{
    /**
     * 管理 API 僅開放已驗證帳號，提供前端明確的轉址識別碼。
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => '請先驗證電子郵件。',
                'code' => 'email_verification_required',
            ], 403);
        }

        return $next($request);
    }
}
