<?php

namespace App\Repositories;

use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;

/**
 * 物品圖片資料存取層
 * 負責處理 ItemImage 模型的資料庫操作
 */
class ItemImageRepository implements ItemImageRepositoryInterface
{
    /**
     * 根據 UUID 查詢圖片
     *
     * @param string $uuid 圖片 UUID
     * @return ItemImage|null
     */
    public function findByUuid(string $uuid): ?ItemImage
    {
        return ItemImage::where('uuid', $uuid)->first();
    }

    /**
     * 增加圖片使用次數
     *
     * @param ItemImage $image 圖片實例
     * @return void
     */
    public function incrementUsageCount(ItemImage $image): void
    {
        $image->increment('usage_count');
    }

    /**
     * 更新圖片狀態
     *
     * @param ItemImage $image 圖片實例
     * @param string $status 狀態
     * @return void
     */
    public function updateStatus(ItemImage $image, string $status): void
    {
        $image->status = $status;
        $image->save();
    }
}
