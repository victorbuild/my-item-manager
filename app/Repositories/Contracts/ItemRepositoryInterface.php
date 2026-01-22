<?php

namespace App\Repositories\Contracts;

use App\Models\Item;

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
}
