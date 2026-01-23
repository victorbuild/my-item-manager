<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * 處理註冊請求
     *
     * @param  RegisterRequest  $request  註冊請求（已驗證）
     * @return JsonResponse { user: User }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json(['user' => $user]);
    }

    /**
     * 處理登入請求
     *
     * @param  LoginRequest  $request  登入請求（已驗證）
     *
     * @throws \Illuminate\Auth\AuthenticationException 認證失敗時由 AuthService 拋出
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException 嘗試次數過多時由 AuthService 拋出
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $this->authService->attemptLogin(
            $credentials,
            $request->ip(),
            $request->userAgent()
        );

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => '登入成功',
        ]);
    }

    /**
     * 取得當前已登入使用者
     *
     * @return JsonResponse 當前使用者物件，未登入則 null
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * 處理登出請求
     *
     * @return JsonResponse { message: "Logged out" }
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
