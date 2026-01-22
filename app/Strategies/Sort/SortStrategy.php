<?php

namespace App\Strategies\Sort;

use Illuminate\Database\Eloquent\Builder;

/**
 * 排序策略介面
 *
 * 定義所有排序策略必須實作的方法
 */
interface SortStrategy
{
    /**
     * 應用排序邏輯到查詢建構器
     *
     * @param Builder $query
     * @return void
     */
    public function apply(Builder $query): void;
}
