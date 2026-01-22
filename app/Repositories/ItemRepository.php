<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;

/**
 * 物品資料存取層
 * 負責處理 Item 模型的資料庫操作
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * 建立物品
     *
     * @param array $data 物品資料
     * @param int $userId 用戶 ID
     * @return Item
     */
    public function create(array $data, int $userId): Item
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
        return $item->fresh(['images', 'units', 'category', 'product.category']);
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
}
