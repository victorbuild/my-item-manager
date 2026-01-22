<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;

/**
 * 物品圖片服務
 * 負責處理物品與圖片的關聯、圖片狀態更新等業務邏輯
 */
class ItemImageService
{
    /**
     * 將圖片附加到物品
     *
     * @param Item $item 物品實例
     * @param array $images 圖片陣列，格式：[['uuid' => '...', 'status' => 'new'], ...]
     * @return void
     */
    public function attachImagesToItem(Item $item, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $loopIndex = 0;
        foreach ($images as $imgObj) {
            // 只處理狀態為 'new' 且有 UUID 的圖片
            if (($imgObj['status'] ?? null) !== 'new' || empty($imgObj['uuid'])) {
                continue;
            }

            $uuid = $imgObj['uuid'];

            // 使用已存在的圖片 UUID 建立多對多關聯
            $item->images()->attach($uuid, [
                'sort_order' => ++$loopIndex,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 更新圖片使用次數和狀態
            $this->incrementImageUsage($uuid);
        }
    }

    /**
     * 增加圖片使用次數
     *
     * @param string $uuid 圖片 UUID
     * @return void
     */
    private function incrementImageUsage(string $uuid): void
    {
        $image = ItemImage::where('uuid', $uuid)->first();
        if ($image) {
            $image->increment('usage_count');
            if ($image->status === ItemImage::STATUS_DRAFT) {
                $image->status = ItemImage::STATUS_USED;
                $image->save();
            }
        }
    }
}
