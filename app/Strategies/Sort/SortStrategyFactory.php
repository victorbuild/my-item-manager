<?php

namespace App\Strategies\Sort;

use InvalidArgumentException;

/**
 * 排序策略工廠
 *
 * 根據排序模式建立對應的策略實例
 */
class SortStrategyFactory
{
    /**
     * 根據排序模式建立對應的策略
     *
     * @param string $sortMode
     * @return SortStrategy
     * @throws InvalidArgumentException
     */
    public function create(string $sortMode): SortStrategy
    {
        return match ($sortMode) {
            'discarded' => new DiscardedSortStrategy(),
            'price_asc' => new PriceAscSortStrategy(),
            'price_desc' => new PriceDescSortStrategy(),
            'name_asc' => new NameAscSortStrategy(),
            'default' => new DefaultSortStrategy(),
            default => throw new InvalidArgumentException("不支援的排序模式: {$sortMode}"),
        };
    }
}
