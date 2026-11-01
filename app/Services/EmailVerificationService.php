<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class EmailVerificationService
{
    /**
     * 寄送驗證信；帳號建立與寄信結果分離，並限制同帳號的寄信頻率。
     */
    public function send(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        return Cache::lock('verification-send-lock:' . $user->getKey(), 30)->get(function () use ($user): bool {
            $minuteKey = 'verification-send-minute:' . $user->getKey();
            $hourKey = 'verification-send-hour:' . $user->getKey();
            foreach ([$minuteKey => 1, $hourKey => 6] as $key => $limit) {
                if (RateLimiter::tooManyAttempts($key, $limit)) {
                    throw new ThrottleRequestsException('寄信過於頻繁，請稍後再試。', null, [
                        'Retry-After' => RateLimiter::availableIn($key),
                    ]);
                }
            }
            RateLimiter::hit($minuteKey, 60);
            RateLimiter::hit($hourKey, 3600);
            try {
                $user->sendEmailVerificationNotification();

                return true;
            } catch (Throwable $exception) {
                report($exception);

                return false;
            }
        }) ?? false;
    }
}
