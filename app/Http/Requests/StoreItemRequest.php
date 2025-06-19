<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreItemRequest extends FormRequest
{
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
            'quantity' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric',
            'purchased_at' => 'nullable|date',
            'received_at' => 'nullable|date',
            'used_at' => 'nullable|date',
            'discarded_at' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'images' => 'nullable|array|max:9',
            'images.*.path' => 'required|string',
            'images.*.status' => 'required|in:new,original,deleted',
            'images.*.id' => 'nullable|integer',
            'barcode' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id',
        ];
    }
}
