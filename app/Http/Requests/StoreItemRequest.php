<?php

namespace App\Http\Requests;

use Carbon\Carbon;
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
            'quantity' => 'nullable|integer|min:1|max:100',
            'price' => 'nullable|numeric',
            'purchased_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $purchasedDate = Carbon::parse($value)->startOfDay();
                        $today = Carbon::today();
                        if ($purchasedDate->gt($today)) {
                            $fail('購買日期不能超過今天。');
                        }

                        $receivedAt = $this->input('received_at');
                        if ($receivedAt) {
                            $receivedDate = Carbon::parse($receivedAt)->startOfDay();
                            if ($purchasedDate->gt($receivedDate)) {
                                $fail('購買日期不能晚於到貨日期。');
                            }
                        }
                    }
                },
            ],
            'received_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $receivedDate = Carbon::parse($value)->startOfDay();
                        $today = Carbon::today();
                        if ($receivedDate->gt($today)) {
                            $fail('到貨日期不能超過今天。');
                        }

                        $purchasedAt = $this->input('purchased_at');
                        $usedAt = $this->input('used_at');

                        if ($purchasedAt) {
                            $purchasedDate = Carbon::parse($purchasedAt)->startOfDay();
                            if ($receivedDate->lt($purchasedDate)) {
                                $fail('到貨日期不能早於購買日期。');
                            }
                        }
                        if ($usedAt) {
                            $usedDate = Carbon::parse($usedAt)->startOfDay();
                            if ($receivedDate->gt($usedDate)) {
                                $fail('到貨日期不能晚於開始使用日期。');
                            }
                        }
                    }
                },
            ],
            'used_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $usedDate = Carbon::parse($value)->startOfDay();
                        $today = Carbon::today();
                        if ($usedDate->gt($today)) {
                            $fail('開始使用日期不能超過今天。');
                        }

                        $purchasedAt = $this->input('purchased_at');
                        $receivedAt = $this->input('received_at');
                        $discardedAt = $this->input('discarded_at');

                        // 開始使用日期不能早於購買日期
                        if ($purchasedAt) {
                            $purchasedDate = Carbon::parse($purchasedAt)->startOfDay();
                            if ($usedDate->lt($purchasedDate)) {
                                $fail('開始使用日期不能早於購買日期。');
                            }
                        }

                        // 開始使用日期不能早於到貨日期
                        if ($receivedAt) {
                            $receivedDate = Carbon::parse($receivedAt)->startOfDay();
                            if ($usedDate->lt($receivedDate)) {
                                $fail('開始使用日期不能早於到貨日期。');
                            }
                        }

                        // 開始使用日期不能晚於報廢日期
                        if ($discardedAt) {
                            $discardedDate = Carbon::parse($discardedAt)->startOfDay();
                            if ($usedDate->gt($discardedDate)) {
                                $fail('開始使用日期不能晚於報廢日期。');
                            }
                        }
                    }
                },
            ],
            'discarded_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $discardedDate = Carbon::parse($value)->startOfDay();
                        $today = Carbon::today();
                        if ($discardedDate->gt($today)) {
                            $fail('報廢日期不能超過今天。');
                        }

                        $usedAt = $this->input('used_at');
                        if ($usedAt) {
                            $usedDate = Carbon::parse($usedAt)->startOfDay();
                            if ($discardedDate->lt($usedDate)) {
                                $fail('報廢日期不能早於開始使用日期。');
                            }
                        }
                    }
                },
            ],
            'expiration_date' => 'nullable|date',
            'images' => 'nullable|array|max:9',
            'images.*.uuid' => 'required|uuid',
            'images.*.status' => 'required|in:new',
            'barcode' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id',
        ];
    }
}
