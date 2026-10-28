<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|max:99999999.99',
            'product_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists(Product::class, 'id')->where('user_id', $this->user()?->id),
            ],
            'serial_number' => 'nullable|string|max:255',
            ...$this->itemDateRules($this->route('item') instanceof Item ? $this->route('item') : null),
            'expiration_date' => 'nullable|date_format:Y-m-d|before_or_equal:9999-12-31',
            'discard_note' => 'nullable',
            'is_discarded' => 'boolean',
            'notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.uuid' => 'required|uuid|distinct:ignore_case',
            'images.*.status' => 'required|in:new,original,removed',
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
            'product_id.exists' => '找不到此產品，請重新選擇。',
            'images.*.uuid.distinct' => '同一張圖片不可重複加入同一件物品。',
        ];
    }
}
