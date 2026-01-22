<?php

namespace App\Strategies\Sort;

use Illuminate\Database\Eloquent\Builder;

/**
 * 棄用時間排序策略
 *
 * 按棄用時間降序排列
 */
class DiscardedSortStrategy implements SortStrategy
{
    /**
     * 應用排序邏輯到查詢建構器
     *
     * @param Builder $query
     * @return void
     */
    public function apply(Builder $query): void
    {
        $query->orderByDesc('discarded_at');
    }
}
