<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
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
        if (! empty($images)) {
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
     *
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
            if (! empty($data['images']) && ! empty($items)) {
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
     */
    public function paginateWithFilters(array $filters, int $userId, int $perPage = 10): LengthAwarePaginator
    {
        // 從 Repository 取得帶有篩選條件的查詢建構器
        $query = $this->itemRepository->buildFilteredQuery($userId, $filters);

        // 使用策略模式處理排序（業務邏輯保留在 Service）
        $sortMode = $filters['sort'] ?? 'default';
        $sortStrategy = $this->sortStrategyFactory->create($sortMode);
        $sortStrategy->apply($query);

        return $query->paginate($perPage);
    }

    /**
     * 刪除物品
     *
     * @param Item $item 物品實例
     */
    public function delete(Item $item): void
    {
        // 使用事務確保所有操作（圖片關聯、圖片狀態更新、物品刪除）的原子性
        DB::transaction(function () use ($item) {
            $this->itemRepository->delete($item);
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
     * 取得指定使用者的物品統計資料
     *
     * 用於將統計計算邏輯從 auth() 解耦，方便單元測試與重用。
     *
     * @param int $userId 使用者 ID
     * @param string $period 時間範圍：all, year, month, week, three_months
     * @param int|null $year 年份（當 period 為 year 時使用）
     * @param array<int, string>|null $include 需要額外計算的區塊（可選，null 代表維持既有整包輸出）
     */
    public function getStatisticsForUser(
        int $userId,
        string $period = 'all',
        ?int $year = null,
        ?array $include = null
    ): array {
        $asOf = now()->startOfDay();

        // 計算時間範圍
        [$startDate, $endDate] = $this->buildDateRange($period, $year);

        // 建立時間範圍過濾函數（用於新增物品的判斷）
        $applyCreatedDateFilter = $this->buildCreatedDateFilter($period, $startDate, $endDate);

        // 建立基礎統計
        $statistics = $this->buildBaseStatistics(
            $userId,
            $period,
            $year,
            $startDate,
            $endDate,
            $applyCreatedDateFilter,
            $asOf
        );

        // 載入可選區塊
        $this->loadOptionalSections(
            $statistics,
            $include,
            $userId,
            $startDate,
            $endDate,
            $applyCreatedDateFilter,
            $asOf
        );

        return $statistics;
    }

    /**
     * 建立基礎統計資料
     *
     * @param int $userId 使用者 ID
     * @param string $period 時間範圍
     * @param int|null $year 年份
     * @param Carbon|null $startDate 開始日期
     * @param Carbon|null $endDate 結束日期
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @param Carbon $asOf 統計基準日
     * @return array 基礎統計資料（as_of, totals, status, value_stats, date_range）
     */
    private function buildBaseStatistics(
        int $userId,
        string $period,
        ?int $year,
        ?Carbon $startDate,
        ?Carbon $endDate,
        \Closure $applyCreatedDateFilter,
        Carbon $asOf
    ): array {
        // 計算基礎統計
        $totals = $this->itemRepository->getTotalsStatistics($userId, $applyCreatedDateFilter, $startDate, $endDate);

        // 計算價值統計
        $valueStats = $this->calculateValueStatistics($userId, $applyCreatedDateFilter, $totals['value']);

        // 計算狀態統計
        $statusStats = $this->itemRepository->getStatusCounts($userId, $applyCreatedDateFilter);

        // 計算時間範圍的開始和結束日期
        $dateRange = $this->calculateDateRange($userId, $period, $year, $startDate);

        return [
            'as_of' => $asOf->toDateString(),
            'totals' => $totals,
            'status' => $statusStats,
            'value_stats' => $valueStats,
            'date_range' => $dateRange,
        ];
    }

    /**
     * 載入可選的統計區塊
     *
     * @param array $statistics 統計資料陣列（會直接修改）
     * @param array<int, string>|null $include 需要載入的區塊列表
     * @param int $userId 使用者 ID
     * @param Carbon|null $startDate 開始日期
     * @param Carbon|null $endDate 結束日期
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @param Carbon $asOf 統計基準日
     */
    private function loadOptionalSections(
        array &$statistics,
        ?array $include,
        int $userId,
        ?Carbon $startDate,
        ?Carbon $endDate,
        \Closure $applyCreatedDateFilter,
        Carbon $asOf
    ): void {
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
            $statistics['unused_items'] = $this->processUnusedItems($userId, $applyCreatedDateFilter);
        }

        if (in_array('discarded_cost_stats', $includeSections, true)) {
            $discardedCostData = $this->calculateDiscardedCostStatistics($userId, $startDate, $endDate);
            $statistics['discarded_cost_stats'] = [
                'average_cost_per_day' => $discardedCostData['average_cost_per_day'],
                'top_five' => $discardedCostData['top_five'],
            ];
        }

        if (in_array('in_use_cost_stats', $includeSections, true)) {
            $inUseCostData = $this->calculateInUseCostStatistics($userId, $applyCreatedDateFilter, $asOf);
            $statistics['in_use_cost_stats'] = [
                'average_cost_per_day' => $inUseCostData['average_cost_per_day'],
                'top_five' => $inUseCostData['top_five'],
            ];
        }
    }

    /**
     * 處理未使用物品統計（計算 days_unused）
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return array{count: int, top_five: \Illuminate\Support\Collection}
     */
    private function processUnusedItems(int $userId, \Closure $applyCreatedDateFilter): array
    {
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

        return [
            'count' => $unusedItemsData['count'],
            'top_five' => $unusedItemsWithDays,
        ];
    }

    /**
     * 建立時間範圍（開始和結束日期）
     *
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
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @param float $totalValue 總支出
     */
    private function calculateValueStatistics(int $userId, \Closure $applyCreatedDateFilter, float $totalValue): array
    {
        // 從 Repository 取得查詢資料
        $valueData = $this->itemRepository->getValueStatisticsData($userId, $applyCreatedDateFilter);
        $effectiveExpense = $valueData['effective_expense'];
        $discardedItemsInPeriod = $valueData['discarded_items'];

        // 支出效率：有效支出 / 總支出
        $expenseEfficiency = $totalValue > 0
            ? round(($effectiveExpense / $totalValue) * 100, 1)
            : 0;

        // 計算棄用物品平均使用成本
        $totalDiscardedCost = 0;
        $totalUsageDays = 0;

        foreach ($discardedItemsInPeriod as $item) {
            $usageDays = $this->calculateUsageDaysToDiscarded($item);

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
     * @param int $userId 使用者 ID
     * @param Carbon|null $startDate 開始日期
     * @param Carbon|null $endDate 結束日期
     */
    private function calculateDiscardedCostStatistics(
        int $userId,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): array {
        // 從 Repository 取得已棄用物品列表
        $discardedItemsList = $this->itemRepository->getDiscardedItemsForCost($userId, $startDate, $endDate);

        return $this->calculateItemCosts($discardedItemsList, true);
    }

    /**
     * 計算使用中物品成本統計
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @param Carbon $asOf 統計基準日
     */
    private function calculateInUseCostStatistics(
        int $userId,
        \Closure $applyCreatedDateFilter,
        Carbon $asOf
    ): array {
        $inUseItemsList = $this->itemRepository->getInUseItemsForCost($userId, $applyCreatedDateFilter);

        return $this->calculateItemCosts($inUseItemsList, false, $asOf);
    }

    /**
     * 計算物品的每日成本
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
     * @param bool $isDiscarded 是否為已棄用物品
     * @param Carbon|null $asOf 統計基準日（使用中物品用：以 asOf 00:00 到 asOf+1 00:00 計算）
     */
    private function calculateItemCosts($items, bool $isDiscarded, ?Carbon $asOf = null): array
    {
        return $isDiscarded
            ? $this->calculateDiscardedItemCosts($items)
            : $this->calculateInUseItemCosts($items, $asOf ?? now()->startOfDay());
    }

    /**
     * 計算已棄用物品的每日成本
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
     */
    private function calculateDiscardedItemCosts($items): array
    {
        $totalCost = 0;
        $totalDays = 0;
        $itemsWithCost = [];

        /** @var \App\Models\Item $item */
        foreach ($items as $item) {
            $usageDays = $this->calculateUsageDaysToDiscarded($item);
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

        return $this->formatItemCostsResult($itemsWithCost, $totalCost, $totalDays);
    }

    /**
     * 計算使用中物品的每日成本
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
     * @param Carbon $asOf 統計基準日（以 asOf 00:00 到 asOf+1 00:00 計算）
     */
    private function calculateInUseItemCosts($items, Carbon $asOf): array
    {
        $totalCost = 0;
        $totalDays = 0;
        $itemsWithCost = [];

        $endAt = $asOf->copy()->addDay();

        /** @var \App\Models\Item $item */
        foreach ($items as $item) {
            $usageDays = $this->calculateUsageDaysToDate($item, $endAt);
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

        return $this->formatItemCostsResult($itemsWithCost, $totalCost, $totalDays);
    }

    /**
     * 格式化物品成本統計結果
     *
     * @param array $itemsWithCost 包含物品和成本資訊的陣列
     * @param float $totalCost 總成本
     * @param float $totalDays 總天數
     */
    private function formatItemCostsResult(array $itemsWithCost, float $totalCost, float $totalDays): array
    {
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
     * 計算物品從開始日期到棄用日期的使用天數
     *
     * @param Item $item 物品實例
     * @return float 使用天數，若無法計算則回傳 0
     */
    private function calculateUsageDaysToDiscarded(Item $item): float
    {
        if (! $item->discarded_at) {
            return 0;
        }

        $endDate = Carbon::parse($item->discarded_at);
        $startDate = $this->getItemStartDate($item);

        if (! $startDate) {
            return 0;
        }

        return $startDate->diffInDays($endDate) + 1;
    }

    /**
     * 計算物品從開始日期到指定日期的使用天數
     *
     * @param Item $item 物品實例
     * @param Carbon $endDate 結束日期
     * @return float 使用天數，若無法計算則回傳 0
     */
    private function calculateUsageDaysToDate(Item $item, Carbon $endDate): float
    {
        $startDate = $this->getItemStartDate($item);

        if (! $startDate) {
            return 0;
        }

        $startAt = $startDate->startOfDay();

        return $startAt->diffInDays($endDate);
    }

    /**
     * 取得物品的開始日期（優先順序：used_at > received_at > purchased_at > created_at）
     *
     * @param Item $item 物品實例
     * @return Carbon|null 開始日期，若無則回傳 null
     */
    private function getItemStartDate(Item $item): ?Carbon
    {
        if ($item->used_at) {
            return Carbon::parse($item->used_at);
        }
        if ($item->received_at) {
            return Carbon::parse($item->received_at);
        }
        if ($item->purchased_at) {
            return Carbon::parse($item->purchased_at);
        }
        if ($item->created_at) {
            return Carbon::parse($item->created_at);
        }

        return null;
    }

    /**
     * 計算時間範圍的開始和結束日期
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
            $firstItemCreatedAt = $this->itemRepository->getFirstItemCreatedAt($userId);
            if ($firstItemCreatedAt) {
                $start = $firstItemCreatedAt->copy()->startOfDay();
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
