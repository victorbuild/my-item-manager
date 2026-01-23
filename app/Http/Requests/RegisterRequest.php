<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 註冊請求驗證
 */
class RegisterRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限進行此請求
     *
     * 註冊請求允許任何人嘗試（未登入使用者即可註冊）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:60',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
