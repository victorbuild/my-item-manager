<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateItemRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|max:99999999.99',
            'serial_number' => 'nullable|string|max:255',
            ...$this->itemDateRules($this->route('item') instanceof Item ? $this->route('item') : null),
            'expiration_date' => 'nullable|date_format:Y-m-d|before_or_equal:9999-12-31',
            'discard_note' => 'nullable',
            'is_discarded' => 'boolean',
            'notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.uuid' => 'required|uuid',
            'images.*.status' => 'required|in:new,original,removed',
        ];
    }
}
