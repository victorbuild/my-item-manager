<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ExpiringSoonRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限進行此請求
     *
     * 此端點允許任何已登入的使用者查詢自己的過期物品。
     * 認證檢查由路由中間件 `auth:sanctum` 處理（未登入會返回 401）。
     * 此方法只檢查權限（已認證但無權限會返回 403），此端點無特殊權限要求，返回 true。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 在驗證前準備資料（設定預設值）
     *
     * 如果請求中沒有提供 `days` 或 `per_page`，則設定預設值。
     * 這樣驗證時會包含這些欄位，`validated()` 方法也會包含預設值。
     * 注意：只有在值不存在時才設定預設值，避免覆蓋傳入的值。
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('days')) {
            $this->merge(['days' => 30]);
        }
        if (!$this->has('per_page')) {
            $this->merge(['per_page' => 20]);
        }
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'days' => 'nullable|integer|min:1|max:1095',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
