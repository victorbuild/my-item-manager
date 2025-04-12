<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'category_id' => 'nullable|exists:categories,id',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'units' => 'nullable|array',
            'units.*' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ];
    }
}
