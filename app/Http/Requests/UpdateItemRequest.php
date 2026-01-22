<?php

namespace App\Http\Requests;

use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * 取得現有模型實例的日期值（如果請求中沒有提供）
     */
    private function getExistingDateValue(string $field): ?string
    {
        // 如果請求中有提供該欄位，返回 null（使用請求中的值）
        if ($this->has($field)) {
            return null;
        }

        // 嘗試從路由參數中獲取模型
        $item = $this->route('item');
        if ($item instanceof Item && $item->$field) {
            return Carbon::parse($item->$field)->format('Y-m-d');
        }

        return null;
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
            'price' => 'nullable|numeric',
            'serial_number' => 'nullable|string|max:255',
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

                        // 取得購買日期（請求中的值或現有值）
                        $purchasedAt = $this->input('purchased_at') ?? $this->getExistingDateValue('purchased_at');
                        // 取得開始使用日期（請求中的值或現有值）
                        $usedAt = $this->input('used_at') ?? $this->getExistingDateValue('used_at');

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

                        // 取得購買日期（請求中的值或現有值）
                        $purchasedAt = $this->input('purchased_at') ?? $this->getExistingDateValue('purchased_at');
                        // 取得到貨日期（請求中的值或現有值）
                        $receivedAt = $this->input('received_at') ?? $this->getExistingDateValue('received_at');
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

                        // 取得開始使用日期（請求中的值或現有值）
                        $usedAt = $this->input('used_at') ?? $this->getExistingDateValue('used_at');
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
            'discard_note' => 'nullable',
            'is_discarded' => 'boolean',
            'notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.uuid' => 'required|uuid',
            'images.*.status' => 'required|in:new,original,removed',
        ];
    }
}
