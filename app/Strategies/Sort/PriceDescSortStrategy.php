<?php

namespace App\Strategies\Sort;

use Illuminate\Database\Eloquent\Builder;

/**
 * 價格降序排序策略
 *
 * 按價格降序排列
 */
class PriceDescSortStrategy implements SortStrategy
{
    /**
     * 應用排序邏輯到查詢建構器
     *
     * @param Builder $query
     * @return void
     */
    public function apply(Builder $query): void
    {
        $query->orderByDesc('price');
    }
}
