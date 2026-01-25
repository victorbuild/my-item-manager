<?php

namespace App\Repositories\Contracts;

use App\Models\Item;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 物品資料存取介面
 * 定義 Item 資料存取的方法契約
 */
interface ItemRepositoryInterface
{
    /**
     * 更新物品
     *
     * @param Item $item 物品實例
     * @param array $data 更新資料
     */
    public function update(Item $item, array $data): Item;

    /**
     * 批次建立物品
     *
     * @param array $data 物品資料
     * @param int $quantity 建立數量
     * @param int $userId 用戶 ID
     * @return array{items: array<Item>, item: Item|null, quantity: int} items 為所有建立的物品，item 為第一個物品（向後相容）
     */
    public function createBatch(array $data, int $quantity, int $userId): array;

    /**
     * 根據 short_id 查詢物品（找不到時拋出異常）
     *
     * @param string $shortId 物品 short_id
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByShortIdOrFail(string $shortId): Item;

    /**
     * 查詢近期過期的商品（尚未棄用且有過期日期）
     *
     * @param int $days 未來幾天內要過期
     * @param int $perPage 每頁筆數
     * @param int $userId 使用者 ID
     */
    public function getExpiringSoonItems(
        int $days,
        int $perPage,
        int $userId
    ): LengthAwarePaginator;

    /**
     * 計算所有日期範圍的統計
     *
     * @param array $ranges 日期範圍陣列，例如 [7, 30, 90, 180, 365, 1095]
     * @param int $userId 使用者 ID
     */
    public function getRangeStatistics(array $ranges, int $userId): array;

    /**
     * 查詢所有有過期日期的商品（尚未棄用且有過期日期，不限制日期範圍）
     *
     * @param int $userId 使用者 ID
     */
    public function countItemsWithExpirationDate(int $userId): int;

    /**
     * 取得價格最昂貴的前五名
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     */
    public function getTopExpensiveItems(int $userId, Closure $applyCreatedDateFilter): Collection;

    /**
     * 取得尚未使用的物品（count + top5）
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return array{count: int, top_five: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item>}
     */
    public function getUnusedItems(int $userId, Closure $applyCreatedDateFilter): array;

    /**
     * 計算狀態統計
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return array{in_use: int, unused: int, pre_arrival: int, used_discarded: int, unused_discarded: int}
     */
    public function getStatusCounts(int $userId, Closure $applyCreatedDateFilter): array;

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
        ?\Carbon\Carbon $startDate,
        ?\Carbon\Carbon $endDate
    ): array;

    /**
     * 取得價值統計的查詢資料（有效支出、棄用物品列表）
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return array{
     *     effective_expense: float,
     *     discarded_items: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item>
     * }
     */
    public function getValueStatisticsData(int $userId, Closure $applyCreatedDateFilter): array;

    /**
     * 取得已棄用物品列表（用於成本統計）
     *
     * @param int $userId 使用者 ID
     * @param \Carbon\Carbon|null $startDate 開始日期
     * @param \Carbon\Carbon|null $endDate 結束日期
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item>
     */
    public function getDiscardedItemsForCost(
        int $userId,
        ?\Carbon\Carbon $startDate,
        ?\Carbon\Carbon $endDate
    ): Collection;

    /**
     * 取得使用中物品列表（用於成本統計）
     *
     * @param int $userId 使用者 ID
     * @param \Closure $applyCreatedDateFilter 建立日期過濾函數
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item>
     */
    public function getInUseItemsForCost(
        int $userId,
        Closure $applyCreatedDateFilter
    ): Collection;

    /**
     * 取得第一個物品的創建日期（用於計算時間範圍）
     *
     * @param int $userId 使用者 ID
     * @return \Carbon\Carbon|null 第一個物品的創建日期，若無則回傳 null
     */
    public function getFirstItemCreatedAt(int $userId): ?\Carbon\Carbon;

    /**
     * 建立帶有篩選條件的查詢建構器（用於分頁查詢）
     *
     * @param int $userId 使用者 ID
     * @param array $filters 篩選條件（product_short_id, search, category_id, statuses）
     */
    public function buildFilteredQuery(int $userId, array $filters): \Illuminate\Database\Eloquent\Builder;

    /**
     * 刪除物品（包含圖片關聯處理）
     *
     * @param Item $item 物品實例
     */
    public function delete(Item $item): void;

    /**
     * 附加圖片到物品
     *
     * @param Item $item 物品實例
     * @param string $imageUuid 圖片 UUID
     * @param array<string, mixed> $pivotData Pivot 表額外資料（如 sort_order）
     */
    public function attachImage(Item $item, string $imageUuid, array $pivotData = []): void;

    /**
     * 移除物品的圖片關聯
     *
     * @param Item $item 物品實例
     * @param string $imageUuid 圖片 UUID
     */
    public function detachImage(Item $item, string $imageUuid): void;

    /**
     * 檢查物品是否已有指定圖片
     *
     * @param Item $item 物品實例
     * @param string $imageUuid 圖片 UUID
     */
    public function hasImage(Item $item, string $imageUuid): bool;

    /**
     * 重新載入物品及其關聯
     *
     * @param Item $item 物品實例
     * @param array<string> $relations 要載入的關聯，預設為 ['images', 'category', 'product.category']
     * @return Item 重新載入後的物品實例
     */
    public function refreshWithRelations(
        Item $item,
        array $relations = ['images', 'category', 'product.category']
    ): Item;
}
