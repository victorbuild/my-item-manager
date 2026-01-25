<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 上傳圖片請求驗證
 */
class StoreItemImageRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限進行此請求
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
        $maxSize = config('images.max_size', 10240);

        return [
            'image' => [
                'required',
                'image',
                "max:{$maxSize}",
            ],
        ];
    }

    /**
     * 取得自訂驗證錯誤訊息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMB = round(config('images.max_size', 10240) / 1024, 1);

        return [
            'image.required' => '請選擇要上傳的圖片',
            'image.image' => '上傳的檔案必須是圖片格式',
            'image.max' => "圖片大小不能超過 {$maxSizeMB}MB",
        ];
    }
}
