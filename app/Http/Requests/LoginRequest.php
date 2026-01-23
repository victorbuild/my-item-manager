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
     * 登入請求允許任何人嘗試（因為登入本身是給未登入的使用者使用的）
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // 任何人都可以嘗試登入
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
