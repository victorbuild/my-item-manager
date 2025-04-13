<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => 'required|string|max:60',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
        ]);

        Auth::login($user);

        return response()->json(['user' => $user]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $key = Str::lower($request->input('email')) . '|' . $request->ip();
        $maxAttempts = 5;
        $cooldown = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "嘗試次數過多，請 $seconds 秒後再試"
            ], 429);
        }

        if (!Auth::attempt($credentials)) {
            $attempts = RateLimiter::increment($key); // +1，且延遲設定會自動套用 cooldown
            if ($attempts >= $maxAttempts) {
                RateLimiter::hit($key, $cooldown); // 第五次才鎖住 60 秒
            }
            return response()->json(['message' => '帳號或密碼錯誤'], 401);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return response()->json(['message' => '登入成功']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
