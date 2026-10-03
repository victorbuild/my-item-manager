<?php

namespace App\Http\Requests;

use App\Models\Item;
use Carbon\Carbon;
use Closure;

trait ValidatesItemDates
{
    /**
     * 驗證物品日期上限與所有已填寫日期的先後順序。
     *
     * @return array<string, array<int, string|\Closure>>
     */
    protected function itemDateRules(?Item $item = null): array
    {
        $fields = [
            'purchased_at' => '購買日期',
            'received_at' => '到貨日期',
            'used_at' => '開始使用日期',
            'discarded_at' => '報廢日期',
        ];
        $dates = [];
        foreach ($fields as $field => $label) {
            $dates[$field] = $this->exists($field)
                ? $this->input($field)
                : $item?->$field?->format('Y-m-d');
        }

        $rules = [];
        $keys = array_keys($fields);
        foreach ($fields as $field => $label) {
            $rules[$field] = [
                'bail',
                'nullable',
                'date_format:Y-m-d',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ) use (
                    $field,
                    $label,
                    $fields,
                    $keys,
                    $dates
): void {
                    if ($value > Carbon::today()->addMonthNoOverflow()->format('Y-m-d')) {
                        $fail($label . '不能超過今天後一個月。');
                    }

                    foreach ($keys as $index => $other) {
                        $otherDate = $dates[$other];
                        if (! is_string($otherDate) || ! Carbon::hasFormat($otherDate, 'Y-m-d')) {
                            continue;
                        }
                        $position = array_search($field, $keys, true);
                        if ($index < $position && $value < $otherDate) {
                            $fail($label . '不能早於' . $fields[$other] . '。');
                        } elseif ($index > $position && $value > $otherDate) {
                            $fail($label . '不能晚於' . $fields[$other] . '。');
                        }
                    }
                },
            ];
        }

        return $rules;
    }
}
