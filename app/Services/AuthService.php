<?php

namespace App\Services;

use App\Events\UserLoggedIn;
use App\Events\UserLoginFailed;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * 認證服務
 *
 * 負責登入的 Rate limiting、認證、以及成功/失敗時的處理與事件
 */
class AuthService
{
    private const MAX_ATTEMPTS = 5;

    private const COOLDOWN_SECONDS = 60;

    /**
     * 嘗試登入
     *
     * 檢查 Rate limit、執行認證；失敗時觸發事件並拋出 AuthenticationException，
     * 成功時清除 Rate limit、觸發登入事件並回傳使用者。Session 的 regenerate 由 Controller 負責。
     *
     * @param  array{email: string, password: string}  $credentials  email 與 password
     * @param  string  $ipAddress  客戶端 IP
     * @param  string|null  $userAgent  User-Agent，可為 null
     * @return Authenticatable 登入成功時回傳已認證的使用者
     *
     * @throws AuthenticationException 認證失敗（帳號或密碼錯誤）
     * @throws ThrottleRequestsException 嘗試次數過多，需等候冷卻
     */
    public function attemptLogin(array $credentials, string $ipAddress, ?string $userAgent = null): Authenticatable
    {
        $key = $this->getRateLimitKey($credentials['email'], $ipAddress);

        $this->ensureNotThrottled($key);

        if (! Auth::attempt($credentials)) {
            $this->handleFailedLogin($credentials['email'], $ipAddress, $userAgent, $key);

            throw new AuthenticationException('帳號或密碼錯誤');
        }

        $this->handleSuccessfulLogin($key, $ipAddress, $userAgent);

        $user = Auth::user();
        if ($user === null) {
            throw new \RuntimeException('登入成功後無法取得使用者物件');
        }

        return $user;
    }

    /**
     * 取得 Rate limit 的 key（email|ip）
     */
    private function getRateLimitKey(string $email, string $ip): string
    {
        return Str::lower($email) . '|' . $ip;
    }

    /**
     * 檢查是否已超過嘗試次數，若超過則拋出 ThrottleRequestsException
     */
    private function ensureNotThrottled(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(
                "嘗試次數過多，請 {$seconds} 秒後再試"
            );
        }
    }

    /**
     * 處理登入失敗：increment、必要時 hit、觸發 UserLoginFailed
     */
    private function handleFailedLogin(string $email, string $ipAddress, ?string $userAgent, string $key): void
    {
        $attempts = RateLimiter::increment($key);
        if ($attempts >= self::MAX_ATTEMPTS) {
            RateLimiter::hit($key, self::COOLDOWN_SECONDS);
        }

        event(new UserLoginFailed($email, $ipAddress, $userAgent));
    }

    /**
     * 處理登入成功：清除 Rate limit、觸發 UserLoggedIn
     */
    private function handleSuccessfulLogin(string $key, string $ipAddress, ?string $userAgent): void
    {
        RateLimiter::clear($key);
        event(new UserLoggedIn(Auth::user(), $ipAddress, $userAgent));
    }
}
