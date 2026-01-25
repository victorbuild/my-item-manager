<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 物品資料存取層
 * 負責處理 Item 模型的資料庫操作
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * 建立物品（內部使用，目前僅供 createBatch 呼叫）
     *
     * @param array $data 物品資料
     * @param int $userId 用戶 ID
     * @return Item
     */
    private function create(array $data, int $userId): Item
    {
        $item = Item::create($data);
        $item->user_id = $userId;
        $item->save();

        return $item;
    }

    /**
     * 更新物品
     *
     * @param Item $item 物品實例
     * @param array $data 更新資料
     * @return Item 更新後並重新載入關聯資料的物品實例
     */
    public function update(Item $item, array $data): Item
    {
        $item->update($data);
        return $item->fresh(['images', 'category', 'product.category']);
    }

    /**
     * 批次建立物品
     *
     * @param array $data 物品資料
     * @param int $quantity 建立數量
     * @param int $userId 用戶 ID
     * @return array{items: array<Item>, item: Item|null, quantity: int} items 為所有建立的物品，item 為第一個物品（向後相容）
     */
    public function createBatch(array $data, int $quantity, int $userId): array
    {
        $items = [];
        $firstItem = null;

        for ($i = 0; $i < $quantity; $i++) {
            $item = $this->create($data, $userId);
            $items[] = $item;

            // 記錄第一筆物品（向後相容）
            if ($i === 0) {
                $firstItem = $item;
            }
        }

        // 載入第一筆物品的關聯資料（向後相容）
        if ($firstItem) {
            $firstItem->load(['images', 'category', 'product.category']);
        }

        return [
            'items' => $items,
            'item' => $firstItem,
            'quantity' => $quantity,
        ];
    }

    /**
     * 根據 short_id 查詢物品（找不到時拋出異常）
     *
     * @param string $shortId 物品 short_id
     * @return Item
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByShortIdOrFail(string $shortId): Item
    {
        return Item::with(['images', 'category'])
            ->where('short_id', $shortId)
            ->firstOrFail();
    }

    /**
     * 查詢近期過期的商品（尚未棄用且有過期日期）
     *
     * @param int $days 未來幾天內要過期
     * @param int $perPage 每頁筆數
     * @param int $userId 使用者 ID
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getExpiringSoonItems(
        int $days,
        int $perPage,
        int $userId
    ): LengthAwarePaginator {
        // 使用日期格式，確保比較正確
        $endDate = now()->addDays($days)->format('Y-m-d');

        $query = Item::with(['images', 'product.category'])
            ->where('user_id', $userId)
            // 尚未棄用
            ->whereNull('discarded_at')
            // 有過期日期
            ->whereNotNull('expiration_date')
            // 過期日期在指定範圍內（包含已過期的，到未來指定天數）
            // 使用 whereDate 確保日期比較正確
            ->whereDate('expiration_date', '<=', $endDate)
            // 按過期日期升序排列（即將過期的在前，已過期的也會顯示）
            ->orderBy('expiration_date', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * 計算所有日期範圍的統計
     *
     * @param array $ranges 日期範圍陣列，例如 [7, 30, 90, 180, 365, 1095]
     * @param int $userId 使用者 ID
     * @return array
     */
    public function getRangeStatistics(array $ranges, int $userId): array
    {
        $today = now()->startOfDay();
        $stats = [];

        foreach ($ranges as $days) {
            $endDate = $today->copy()->addDays($days)->endOfDay()->format('Y-m-d');

            $count = Item::where('user_id', $userId)
                ->whereNull('discarded_at')
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', $endDate)
                ->count();

            $stats[$days] = $count;
        }

        return $stats;
    }

    /**
     * 查詢所有有過期日期的商品（尚未棄用且有過期日期，不限制日期範圍）
     *
     * @param int $userId 使用者 ID
     * @return int
     */
    public function countItemsWithExpirationDate(int $userId): int
    {
        return Item::where('user_id', $userId)
            ->whereNull('discarded_at')
            ->whereNotNull('expiration_date')
            ->count();
    }

    /**
     * 取得價格最昂貴的前五名
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopExpensiveItems(int $userId, Closure $applyCreatedDateFilter): Collection
    {
        $query = Item::where('user_id', $userId);
        $query = $applyCreatedDateFilter($query);

        return $query
            ->whereNotNull('price')
            ->orderByDesc('price')
            ->limit(5)
            ->with(['images', 'product'])
            ->get();
    }

    /**
     * 取得尚未使用的物品（count + top5）
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return array{count: int, top_five: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item>}
     */
    public function getUnusedItems(int $userId, Closure $applyCreatedDateFilter): array
    {
        $query = Item::where('user_id', $userId);
        $query = $applyCreatedDateFilter($query);
        $unusedItemsQuery = $query
            ->whereNull('discarded_at')
            ->whereNull('used_at')
            ->whereNotNull('price');

        $unusedCount = (clone $unusedItemsQuery)->count();

        $unusedTopFive = (clone $unusedItemsQuery)
            ->orderByDesc('price')
            ->limit(5)
            ->with(['images', 'product'])
            ->get();

        return [
            'count' => $unusedCount,
            'top_five' => $unusedTopFive,
        ];
    }

    /**
     * 計算狀態統計
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return array{in_use: int, unused: int, pre_arrival: int, used_discarded: int, unused_discarded: int}
     */
    public function getStatusCounts(int $userId, Closure $applyCreatedDateFilter): array
    {
        $baseQuery = Item::where('user_id', $userId);
        $statusFilteredQuery = $applyCreatedDateFilter((clone $baseQuery));

        return [
            'in_use' => (clone $statusFilteredQuery)
                ->status('in_use')
                ->count(),
            'unused' => (clone $statusFilteredQuery)
                ->status('unused')
                ->count(),
            'pre_arrival' => (clone $statusFilteredQuery)
                ->status('pre_arrival')
                ->count(),
            'used_discarded' => (clone $statusFilteredQuery)
                ->status('used_discarded')
                ->count(),
            'unused_discarded' => (clone $statusFilteredQuery)
                ->status('unused_discarded')
                ->count(),
        ];
    }

    /**
     * 計算基礎統計（總數、價值等）
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @param \Carbon\Carbon|null $startDate 開始日期
     * @param \Carbon\Carbon|null $endDate 結束日期
     * @return array{created: int, discarded: int, value: float, discarded_value: float}
     */
    public function getTotalsStatistics(
        int $userId,
        Closure $applyCreatedDateFilter,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): array {
        $baseQuery = Item::where('user_id', $userId);

        $totalCreated = $applyCreatedDateFilter((clone $baseQuery))->count();

        $discardedQuery = (clone $baseQuery)->whereNotNull('discarded_at');
        if ($startDate) {
            $discardedQuery->where('discarded_at', '>=', $startDate);
        }
        if ($endDate) {
            $discardedQuery->where('discarded_at', '<=', $endDate);
        }
        $totalDiscarded = $discardedQuery->count();

        $totalValue = $applyCreatedDateFilter((clone $baseQuery))->sum('price') ?: 0;

        $discardedValueQuery = (clone $baseQuery)->whereNotNull('discarded_at');
        if ($startDate) {
            $discardedValueQuery->where('discarded_at', '>=', $startDate);
        }
        if ($endDate) {
            $discardedValueQuery->where('discarded_at', '<=', $endDate);
        }
        $discardedValue = $discardedValueQuery->sum('price') ?: 0;

        return [
            'created' => $totalCreated,
            'discarded' => $totalDiscarded,
            'value' => $totalValue,
            'discarded_value' => $discardedValue,
        ];
    }
}
