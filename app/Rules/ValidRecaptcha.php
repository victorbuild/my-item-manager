<?php

namespace App\Rules;

use App\Services\RecaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * reCAPTCHA v3 驗證規則
 *
 * 透過 RecaptchaService 驗證 token，score 低於門檻視為驗證失敗。
 */
class ValidRecaptcha implements ValidationRule
{
    public function __construct(
        private readonly RecaptchaService $recaptchaService,
        private readonly string $action = 'login',
    ) {
    }

    /**
     * 執行驗證
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->recaptchaService->verify($value, $this->action)) {
            $fail('人機驗證失敗，請重新整理頁面再試。');
        }
    }
}
