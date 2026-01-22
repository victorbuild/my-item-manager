<?php

namespace App\Strategies\Sort;

use Illuminate\Database\Eloquent\Builder;

/**
 * 預設排序策略
 *
 * 按 ID 降序排列
 */
class DefaultSortStrategy implements SortStrategy
{
    /**
     * 應用排序邏輯到查詢建構器
     *
     * @param Builder $query
     * @return void
     */
    public function apply(Builder $query): void
    {
        $query->orderByDesc('id');
    }
}
