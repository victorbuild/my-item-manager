<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 登入請求驗證
 */
class LoginRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限進行此請求
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // 登入不需要認證
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }
}
