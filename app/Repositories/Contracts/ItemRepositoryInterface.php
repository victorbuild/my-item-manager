<?php

namespace App\Repositories\Contracts;

use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 物品資料存取介面
 * 定義 Item 資料存取的方法契約
 */
interface ItemRepositoryInterface
{
    /**
     * 建立物品
     *
     * @param array $data 物品資料
     * @param int $userId 用戶 ID
     * @return Item
     */
    public function create(array $data, int $userId): Item;

    /**
     * 更新物品
     *
     * @param Item $item 物品實例
     * @param array $data 更新資料
     * @return Item
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
     * @return Item
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByShortIdOrFail(string $shortId): Item;

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
    ): LengthAwarePaginator;

    /**
     * 計算所有日期範圍的統計
     *
     * @param array $ranges 日期範圍陣列，例如 [7, 30, 90, 180, 365, 1095]
     * @param int $userId 使用者 ID
     * @return array
     */
    public function getRangeStatistics(array $ranges, int $userId): array;

    /**
     * 查詢所有有過期日期的商品（尚未棄用且有過期日期，不限制日期範圍）
     *
     * @param int $userId 使用者 ID
     * @return int
     */
    public function countItemsWithExpirationDate(int $userId): int;
}
