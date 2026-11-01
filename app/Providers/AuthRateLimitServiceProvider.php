<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AuthRateLimitServiceProvider extends ServiceProvider
{
    /**
     * 註冊認證入口的分層限流規則，使用共用快取計數。
     */
    public function boot(): void
    {
        RateLimiter::for('registration', function (Request $request): array {
            $ip = $request->ip();
            $email = $request->input('email');
            // 正規化後雜湊，不把信箱明文放進快取鍵；非字串輸入共用空值額度。
            $emailKey = hash('sha256', is_string($email) ? Str::lower(trim($email)) : '');

            return [
                Limit::perMinute(config('auth_rate_limits.registration.per_minute'))
                    ->by('registration-minute:' . $ip),
                Limit::perHour(config('auth_rate_limits.registration.per_hour'))
                    ->by('registration-hour:' . $ip),
                Limit::perMinute(config('auth_rate_limits.registration.per_ip_email_minute'))
                    ->by('registration-ip-email:' . $ip . ':' . $emailKey),
            ];
        });

        RateLimiter::for('verification-ip', fn (Request $request): array => [
            Limit::perMinute(30)->by('verification-ip-minute:' . $request->ip()),
            Limit::perHour(180)->by('verification-ip-hour:' . $request->ip()),
        ]);

        RateLimiter::for('login-ip', fn (Request $request): array => [
            Limit::perMinute(config('auth_rate_limits.login.per_minute'))
                ->by('login-ip-minute:' . $request->ip()),
            Limit::perHour(config('auth_rate_limits.login.per_hour'))
                ->by('login-ip-hour:' . $request->ip()),
        ]);
    }
}
