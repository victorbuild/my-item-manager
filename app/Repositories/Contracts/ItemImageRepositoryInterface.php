<?php

namespace App\Repositories\Contracts;

use App\Models\ItemImage;

/**
 * 物品圖片資料存取介面
 * 定義 ItemImage 資料存取的方法契約
 */
interface ItemImageRepositoryInterface
{
    /**
     * 根據 UUID 查詢圖片
     *
     * @param string $uuid 圖片 UUID
     */
    public function findByUuid(string $uuid): ?ItemImage;

    /**
     * 增加圖片使用次數
     *
     * @param ItemImage $image 圖片實例
     */
    public function incrementUsageCount(ItemImage $image): void;

    /**
     * 減少圖片使用次數
     *
     * @param ItemImage $image 圖片實例
     */
    public function decrementUsageCount(ItemImage $image): void;

    /**
     * 更新圖片狀態
     *
     * @param ItemImage $image 圖片實例
     * @param string $status 狀態
     */
    public function updateStatus(ItemImage $image, string $status): void;

    /**
     * 建立新的圖片記錄
     *
     * @param array<string, mixed> $data 圖片資料
     */
    public function create(array $data): ItemImage;

    /**
     * 刪除圖片記錄
     *
     * @param ItemImage $image 圖片實例
     */
    public function delete(ItemImage $image): bool;
}
