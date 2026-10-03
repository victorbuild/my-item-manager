<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreItemRequest extends FormRequest
{
    use ValidatesItemDates;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
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
            'images.*.uuid' => 'required|uuid',
            'images.*.status' => 'required|in:new',
            'barcode' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id',
        ];
    }
}
