<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v3 驗證服務
 *
 * 呼叫 Google siteverify API 驗證 token，回傳 score。
 */
class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const MIN_SCORE = 0.5;

    public function __construct(private readonly string $secretKey)
    {
    }

    /**
     * 驗證 reCAPTCHA v3 token
     *
     * @param  string  $token  前端取得的 reCAPTCHA token
     * @param  string  $action  預期的 action 名稱（用於比對）
     * @return bool score >= 0.5 且 action 相符時回傳 true
     */
    public function verify(string $token, string $action = 'login'): bool
    {
        try {
            $response = Http::asForm()->post(self::VERIFY_URL, [
                'secret' => $this->secretKey,
                'response' => $token,
            ]);

            if (! $response->successful()) {
                Log::warning('reCAPTCHA API 回應失敗', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                return false;
            }

            if (($data['action'] ?? '') !== $action) {
                Log::warning('reCAPTCHA action 不符', [
                    'expected' => $action,
                    'received' => $data['action'] ?? null,
                ]);

                return false;
            }

            return ($data['score'] ?? 0.0) >= self::MIN_SCORE;
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA 驗證例外', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
