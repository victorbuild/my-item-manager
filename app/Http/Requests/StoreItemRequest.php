<?php

namespace App\Http\Requests;

use App\Models\ItemImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    use ValidatesItemDates;

    /**
     * 確認使用者已登入並可提出此請求。
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * 取得物品資料與圖片關聯的驗證規則。
     *
     * @return array<string, string|array<int, string|\Closure|\Illuminate\Validation\Rules\Exists>>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1|max:' . config('app.max_item_quantity'),
            'price' => 'nullable|numeric|max:99999999.99',
            ...$this->itemDateRules(),
            'expiration_date' => 'nullable|date_format:Y-m-d|before_or_equal:9999-12-31',
            'images' => 'nullable|array|max:9',
            'images.*.uuid' => [
                'bail',
                'required',
                'uuid',
                'distinct:ignore_case',
                Rule::exists(ItemImage::class, 'uuid')->where('user_id', $this->user()?->id),
            ],
            'images.*.status' => 'required|in:new',
            'barcode' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id',
        ];
    }

    /**
     * 取得重複圖片的驗證提示。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.uuid.exists' => '找不到此圖片，請重新選擇。',
            'images.*.uuid.distinct' => '同一張圖片不可重複加入同一件物品。',
        ];
    }
}
