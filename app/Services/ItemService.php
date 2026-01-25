<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\ItemImageService;
use App\Strategies\Sort\SortStrategyFactory;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemService
{
    public function __construct(
        private readonly int $maxItemQuantity,
        private readonly ItemImageService $itemImageService,
        private readonly SortStrategyFactory $sortStrategyFactory,
        private readonly ItemRepositoryInterface $itemRepository
    ) {
    }

    /**
     * 更新物品
     *
     * @param Item $item 物品實例
     * @param array $data 驗證後的資料
     * @param array $images 圖片陣列，格式：[['uuid' => '...', 'status' => 'new|removed|original'], ...]，空陣列表示不更新圖片
     * @return Item 更新後的物品實例
     */
    public function update(Item $item, array $data, array $images = []): Item
    {
        // 更新物品基本資料（Repository 會自動 fresh 關聯資料）
        $item = $this->itemRepository->update($item, $data);

        // 如果有提供圖片（非空陣列），同步圖片（會自動 fresh 關聯資料）
        if (!empty($images)) {
            $item = $this->itemImageService->syncItemImages($item, $images);
        }

        return $item;
    }

    /**
     * 計算建立數量（含上限檢查）
     *
     * @param array $data 驗證後的資料
     * @return int 處理後的數量
     */
    public function calculateQuantity(array $data): int
    {
        $quantity = max((int) ($data['quantity'] ?? 1), 1);
        return min($quantity, $this->maxItemQuantity);
    }

    /**
     * 批次建立物品並關聯圖片
     *
     * @param array $data 物品資料
     * @param int $quantity 建立數量
     * @param int $userId 用戶 ID
     * @return array{items: array<Item>, item: Item|null, quantity: int} items 為所有建立的物品，item 為第一個物品（向後相容）
     * @throws \Exception 當資料庫操作或圖片關聯失敗時拋出，已自動執行 rollback
     */
    public function createBatch(array $data, int $quantity, int $userId): array
    {
        DB::beginTransaction();
        try {
            // 使用 Repository 批次建立物品
            $result = $this->itemRepository->createBatch($data, $quantity, $userId);
            $items = $result['items'];

            // 處理圖片關聯（如果有提供，為所有物品附加圖片）
            if (!empty($data['images']) && !empty($items)) {
                foreach ($items as $item) {
                    $this->itemImageService->attachImagesToItem($item, $data['images']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * 分頁查詢物品（含篩選、排序），僅限指定使用者的物品
     *
     * @param  array  $filters  篩選條件（search, category_id, statuses, sort）
     * @param  int  $userId  使用者 ID（由 Controller 傳入 auth()->id()，便於測試）
     * @param  int  $perPage  每頁筆數
     * @return LengthAwarePaginator
     */
    public function paginateWithFilters(array $filters, int $userId, int $perPage = 10): LengthAwarePaginator
    {
        $query = Item::with(['images', 'product.category'])
            ->where('user_id', $userId);

        // 產品篩選（以 product short_id）
        if (!empty($filters['product_short_id'])) {
            $productShortId = $filters['product_short_id'];
            $query->whereHas('product', function ($q) use ($productShortId) {
                $q->where('short_id', $productShortId);
            });
        }

        // 搜尋關鍵字
        if (!empty($filters['search'])) {
            $query->where('name', 'ILIKE', '%' . $filters['search'] . '%');
        }

        // 分類篩選
        if (array_key_exists('category_id', $filters)) {
            $categoryId = $filters['category_id'];

            if ($categoryId === 'none') {
                $query->withWhereHas('product', function ($q) use ($categoryId) {
                    $q->whereNull('category_id');
                });
            } elseif ($categoryId) {
                $query->withWhereHas('product', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            }
        }

        // 狀態多選篩選
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->status($filters['statuses']);
        }

        // 使用策略模式處理排序
        $sortMode = $filters['sort'] ?? 'default';
        $sortStrategy = $this->sortStrategyFactory->create($sortMode);
        $sortStrategy->apply($query);

        return $query->paginate($perPage);
    }

    public function delete(Item $item): void
    {
        DB::transaction(function () use ($item) {
            foreach ($item->images as $image) {
                $item->images()->detach($image->uuid);
                $image->decrement('usage_count');

                if ($image->usage_count <= 0) {
                    $image->status = 'draft';
                    $image->save();
                }
            }

            $item->delete();
        });
    }

    /**
     * 取得 expiringSoon 端點的統計資料
     *
     * @param int $days 查詢天數（用於計算範圍統計，但不影響結果）
     * @param int $userId 使用者 ID
     * @return array{range_statistics: array, total_all_with_expiration_date: int}
     */
    public function getExpiringSoonStatistics(int $days, int $userId): array
    {
        $ranges = [7, 30, 90, 180, 365, 1095];

        return [
            'range_statistics' => $this->itemRepository->getRangeStatistics($ranges, $userId),
            'total_all_with_expiration_date' => $this->itemRepository->countItemsWithExpirationDate($userId),
        ];
    }

    /**
     * 取得物品統計資料
     *
     * @param string $period 時間範圍：all, year, month, week, three_months
     * @param int|null $year 年份（當 period 為 year 時使用）
     * @param array<int, string>|null $include 需要額外計算的區塊（可選，逗號分隔後解析而來）
     * @return array
     */
    public function getStatistics(string $period = 'all', ?int $year = null, ?array $include = null): array
    {
        $userId = auth()->id();
        if ($userId === null) {
            return [];
        }

        return $this->getStatisticsForUser($userId, $period, $year, $include);
    }

    /**
     * 取得指定使用者的物品統計資料
     *
     * 用於將統計計算邏輯從 auth() 解耦，方便單元測試與重用。
     *
     * @param int $userId 使用者 ID
     * @param string $period 時間範圍：all, year, month, week, three_months
     * @param int|null $year 年份（當 period 為 year 時使用）
     * @param array<int, string>|null $include 需要額外計算的區塊（可選，null 代表維持既有整包輸出）
     * @return array
     */
    public function getStatisticsForUser(
        int $userId,
        string $period = 'all',
        ?int $year = null,
        ?array $include = null
    ): array {
        $asOf = now()->startOfDay();
        $baseQuery = Item::where('user_id', $userId);

        // 計算時間範圍
        [$startDate, $endDate] = $this->buildDateRange($period, $year);

        // 建立時間範圍過濾函數（用於新增物品的判斷）
        $applyCreatedDateFilter = $this->buildCreatedDateFilter($period, $startDate, $endDate);

        // 計算基礎統計
        $totals = $this->itemRepository->getTotalsStatistics($userId, $applyCreatedDateFilter, $startDate, $endDate);

        // 計算價值統計
        $valueStats = $this->calculateValueStatistics($baseQuery, $applyCreatedDateFilter, $totals['value']);

        // 計算狀態統計
        $statusStats = $this->itemRepository->getStatusCounts($userId, $applyCreatedDateFilter);

        // 計算時間範圍的開始和結束日期
        $dateRange = $this->calculateDateRange($userId, $period, $year, $startDate);

        $statistics = [
            'as_of' => $asOf->toDateString(),
            'totals' => $totals,
            'status' => $statusStats,
            'value_stats' => $valueStats,
            'date_range' => $dateRange,
        ];

        $allowedHeavySections = [
            'top_expensive',
            'unused_items',
            'discarded_cost_stats',
            'in_use_cost_stats',
        ];

        // null 代表維持既有行為：整包輸出（避免前端連動）
        $includeSections = $include === null
            ? $allowedHeavySections
            : array_values(array_intersect($allowedHeavySections, $include));

        if (in_array('top_expensive', $includeSections, true)) {
            $statistics['top_expensive'] = $this->itemRepository->getTopExpensiveItems(
                $userId,
                $applyCreatedDateFilter
            );
        }

        if (in_array('unused_items', $includeSections, true)) {
            $unusedItemsData = $this->itemRepository->getUnusedItems($userId, $applyCreatedDateFilter);
            // 計算 days_unused（這個計算邏輯留在 Service）
            $unusedItemsWithDays = $unusedItemsData['top_five']->map(function (Item $item) {
                $daysUnused = 0;
                $today = now();

                if ($item->received_at) {
                    $daysUnused = round(Carbon::parse($item->received_at)->diffInDays($today), 1);
                } elseif ($item->purchased_at) {
                    $daysUnused = round(Carbon::parse($item->purchased_at)->diffInDays($today), 1);
                } elseif ($item->created_at) {
                    $daysUnused = round(Carbon::parse($item->created_at)->diffInDays($today), 1);
                }

                return [
                    'item' => $item,
                    'days_unused' => $daysUnused,
                ];
            })->values();

            $statistics['unused_items'] = [
                'count' => $unusedItemsData['count'],
                'top_five' => $unusedItemsWithDays,
            ];
        }

        if (in_array('discarded_cost_stats', $includeSections, true)) {
            $discardedCostData = $this->calculateDiscardedCostStatistics($baseQuery, $startDate, $endDate);
            $statistics['discarded_cost_stats'] = [
                'average_cost_per_day' => $discardedCostData['average_cost_per_day'],
                'top_five' => $discardedCostData['top_five'],
            ];
        }

        if (in_array('in_use_cost_stats', $includeSections, true)) {
            $inUseCostData = $this->calculateInUseCostStatistics($baseQuery, $applyCreatedDateFilter, $asOf);
            $statistics['in_use_cost_stats'] = [
                'average_cost_per_day' => $inUseCostData['average_cost_per_day'],
                'top_five' => $inUseCostData['top_five'],
            ];
        }

        return $statistics;
    }

    /**
     * 建立時間範圍（開始和結束日期）
     *
     * @param string $period
     * @param int|null $year
     * @return array [Carbon|null, Carbon|null]
     */
    private function buildDateRange(string $period, ?int $year): array
    {
        $startDate = null;
        $endDate = null;

        if ($period === 'year') {
            if ($year) {
                $startDate = Carbon::create($year, 1, 1)->startOfDay();
                $today = now();
                if ($year === $today->year) {
                    $endDate = $today;
                } else {
                    $endDate = Carbon::create($year, 12, 31)->endOfDay();
                }
            } else {
                $startDate = now()->startOfYear();
            }
        } elseif ($period === 'three_months') {
            $startDate = now()->subMonths(3)->startOfDay();
        } elseif ($period === 'month') {
            $startDate = now()->startOfMonth();
        } elseif ($period === 'week') {
            $startDate = now()->startOfWeek();
        }

        return [$startDate, $endDate];
    }

    /**
     * 建立時間範圍過濾函數（用於新增物品的判斷）
     *
     * @param string $period
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return \Closure
     */
    private function buildCreatedDateFilter(
        string $period,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): \Closure {
        return function ($query) use ($period, $startDate, $endDate) {
            if ($period === 'all') {
                return $query;
            }
            if ($startDate) {
                return $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNotNull('purchased_at')
                            ->where('purchased_at', '>=', $startDate);
                        if ($endDate) {
                            $sub->where('purchased_at', '<=', $endDate);
                        }
                    })->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNull('purchased_at')
                            ->whereNotNull('received_at')
                            ->where('received_at', '>=', $startDate);
                        if ($endDate) {
                            $sub->where('received_at', '<=', $endDate);
                        }
                    })->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNull('purchased_at')
                            ->whereNull('received_at')
                            ->where('created_at', '>=', $startDate);
                        if ($endDate) {
                            $sub->where('created_at', '<=', $endDate);
                        }
                    });
                });
            }
            return $query;
        };
    }


    /**
     * 計算價值統計（總支出、有效支出、支出效率、棄用物品平均使用成本）
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @param \Closure $applyCreatedDateFilter
     * @param float $totalValue
     * @return array
     */
    private function calculateValueStatistics($baseQuery, \Closure $applyCreatedDateFilter, float $totalValue): array
    {
        // 有效支出：範圍內新增的物品中，使用中 + 使用後棄用的總金額
        $effectiveExpenseQuery = (clone $baseQuery);
        $effectiveExpenseQuery = $applyCreatedDateFilter($effectiveExpenseQuery);
        $effectiveExpense = $effectiveExpenseQuery
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('used_at')->whereNull('discarded_at');
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('used_at')->whereNotNull('discarded_at');
                });
            })
            ->sum('price') ?? 0;

        // 支出效率：有效支出 / 總支出
        $expenseEfficiency = $totalValue > 0
            ? round(($effectiveExpense / $totalValue) * 100, 1)
            : 0;

        // 棄用物品平均使用成本（只計算範圍內新增的物品）
        $discardedItemsInPeriod = (clone $baseQuery);
        $discardedItemsInPeriod = $applyCreatedDateFilter($discardedItemsInPeriod);
        $discardedItemsInPeriod = $discardedItemsInPeriod
            ->whereNotNull('discarded_at')
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->get();

        $totalDiscardedCost = 0;
        $totalUsageDays = 0;

        foreach ($discardedItemsInPeriod as $item) {
            $usageDays = 0;

            if ($item->used_at && $item->discarded_at) {
                $usageDays = Carbon::parse($item->used_at)
                    ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
            } elseif ($item->purchased_at && $item->discarded_at) {
                $usageDays = Carbon::parse($item->purchased_at)
                    ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
            } elseif ($item->received_at && $item->discarded_at) {
                $usageDays = Carbon::parse($item->received_at)
                    ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
            } elseif ($item->created_at && $item->discarded_at) {
                $usageDays = Carbon::parse($item->created_at)
                    ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
            }

            if ($usageDays > 0) {
                $totalDiscardedCost += $item->price;
                $totalUsageDays += $usageDays;
            }
        }

        $discardedCostPerDay = $totalUsageDays > 0
            ? round($totalDiscardedCost / $totalUsageDays, 1)
            : 0;

        return [
            'total_expense' => $totalValue,
            'effective_expense' => $effectiveExpense,
            'expense_efficiency' => $expenseEfficiency,
            'discarded_cost_per_day' => $discardedCostPerDay,
        ];
    }



    /**
     * 計算已結案物品成本統計
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    private function calculateDiscardedCostStatistics(
        $baseQuery,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): array {
        $discardedItemsForCost = (clone $baseQuery)
            ->whereNotNull('discarded_at')
            ->whereNotNull('price')
            ->where('price', '>', 0);

        if ($startDate) {
            $discardedItemsForCost->where('discarded_at', '>=', $startDate);
        }
        if ($endDate) {
            $discardedItemsForCost->where('discarded_at', '<=', $endDate);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $discardedItemsList */
        $discardedItemsList = $discardedItemsForCost->get();
        return $this->calculateItemCosts($discardedItemsList, true);
    }

    /**
     * 計算使用中物品成本統計
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @param \Closure $applyCreatedDateFilter
     * @return array
     */
    private function calculateInUseCostStatistics(
        $baseQuery,
        \Closure $applyCreatedDateFilter,
        Carbon $asOf
    ): array {
        $inUseItemsForCost = (clone $baseQuery)
            ->whereNotNull('used_at')
            ->whereNull('discarded_at')
            ->whereNotNull('price')
            ->where('price', '>', 0);

        $inUseItemsForCost = $applyCreatedDateFilter($inUseItemsForCost);
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $inUseItemsList */
        $inUseItemsList = $inUseItemsForCost->get();
        return $this->calculateItemCosts($inUseItemsList, false, $asOf);
    }

    /**
     * 計算物品的每日成本
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
     * @param bool $isDiscarded 是否為已棄用物品
     * @param Carbon|null $asOf 統計基準日（使用中物品用：以 asOf 00:00 到 asOf+1 00:00 計算）
     * @return array
     */
    private function calculateItemCosts($items, bool $isDiscarded, ?Carbon $asOf = null): array
    {
        $totalCost = 0;
        $totalDays = 0;
        $itemsWithCost = [];

        /** @var \App\Models\Item $item */
        foreach ($items as $item) {
            $usageDays = 0;
            $costPerDay = 0;

            if ($isDiscarded) {
                // 已棄用：計算從開始使用（或到貨）到棄用的天數
                if ($item->used_at && $item->discarded_at) {
                    $usageDays = Carbon::parse($item->used_at)
                        ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
                } elseif ($item->received_at && $item->discarded_at) {
                    $usageDays = Carbon::parse($item->received_at)
                        ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
                } elseif ($item->purchased_at && $item->discarded_at) {
                    $usageDays = Carbon::parse($item->purchased_at)
                        ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
                } elseif ($item->created_at && $item->discarded_at) {
                    $usageDays = Carbon::parse($item->created_at)
                        ->diffInDays(Carbon::parse($item->discarded_at)) + 1;
                }
            } else {
                // 使用中：以「使用日 00:00 → asOf+1 00:00」計算（同一天內穩定，便於比對）
                $endAt = ($asOf ?? now()->startOfDay())->copy()->addDay();
                $startAtValue = $item->used_at ?? $item->received_at ?? $item->purchased_at ?? $item->created_at;
                if ($startAtValue) {
                    $startAt = Carbon::parse($startAtValue)->startOfDay();
                    $usageDays = $startAt->diffInDays($endAt);
                }
            }

            $itemPrice = (float) $item->price;
            if ($usageDays > 0 && $itemPrice > 0) {
                $costPerDay = round($itemPrice / $usageDays, 1);
                $totalCost += $itemPrice;
                $totalDays += $usageDays;

                $itemsWithCost[] = [
                    'item' => $item,
                    'cost_per_day' => $costPerDay,
                    'usage_days' => round($usageDays, 1),
                ];
            }
        }

        // 計算平均每日成本
        $averageCostPerDay = $totalDays > 0
            ? round($totalCost / $totalDays, 1)
            : 0;

        // 按每日成本排序，取前五名
        usort($itemsWithCost, function ($a, $b) {
            return $b['cost_per_day'] <=> $a['cost_per_day'];
        });

        $topFive = array_slice($itemsWithCost, 0, 5);

        return [
            'average_cost_per_day' => $averageCostPerDay,
            'top_five' => collect($topFive)->map(function ($data) {
                return [
                    'item' => $data['item'],
                    'cost_per_day' => $data['cost_per_day'],
                    'usage_days' => $data['usage_days'],
                ];
            })->values()->all(),
        ];
    }

    /**
     * 計算時間範圍的開始和結束日期
     *
     * @param int $userId
     * @param string $period
     * @param int|null $year
     * @param Carbon|null $startDate
     * @return array
     */
    private function calculateDateRange(int $userId, string $period, ?int $year, ?Carbon $startDate): array
    {
        $today = now();
        $start = $today->copy()->startOfDay(); // 預設值
        $end = $today;

        if ($period === 'year') {
            if ($year) {
                $start = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
                if ($year === $today->year) {
                    $end = $today;
                } else {
                    $end = \Carbon\Carbon::create($year, 12, 31)->endOfDay();
                }
            } else {
                $start = $today->copy()->startOfYear();
                $end = $today;
            }
        } elseif ($period === 'three_months') {
            $start = $today->copy()->subMonths(3)->startOfDay();
            $end = $today;
        } elseif ($period === 'month') {
            $start = $today->copy()->startOfMonth();
            $end = $today;
        } elseif ($period === 'week') {
            $start = $today->copy()->startOfWeek();
            $end = $today;
        } else {
            // 全部時間範圍，使用第一個物品的創建日期
            $firstItem = Item::where('user_id', $userId)
                ->orderBy('created_at', 'asc')
                ->first();
            if ($firstItem && $firstItem->created_at) {
                $start = Carbon::parse($firstItem->created_at)->startOfDay();
            } else {
                $start = $today->copy()->startOfDay();
            }
            $end = $today;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'start_formatted' => $start->format('Y年m月d日'),
            'end_formatted' => $end->format('Y年m月d日'),
        ];
    }
}
