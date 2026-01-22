<?php

namespace App\Strategies\Sort;

use Illuminate\Database\Eloquent\Builder;

/**
 * 價格升序排序策略
 *
 * 按價格升序排列
 */
class PriceAscSortStrategy implements SortStrategy
{
    /**
     * 應用排序邏輯到查詢建構器
     *
     * @param Builder $query
     * @return void
     */
    public function apply(Builder $query): void
    {
        $query->orderBy('price', 'asc');
    }
}
