<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;

/**
 * 物品圖片服務
 * 負責處理物品與圖片的關聯、圖片狀態更新等業務邏輯
 */
class ItemImageService
{
    public function __construct(
        private readonly ItemImageRepositoryInterface $itemImageRepository
    ) {
    }

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
     * 同步物品的圖片（用於更新操作）
     * 處理新增、移除的圖片，並更新圖片使用次數和狀態
     *
     * @param Item $item 物品實例
     * @param array $images 圖片陣列，格式：[['uuid' => '...', 'status' => 'new|removed|original'], ...]
     * @return Item 同步後並重新載入關聯資料的物品實例
     */
    public function syncItemImages(Item $item, array $images): Item
    {
        if (empty($images)) {
            return $item->fresh(['images', 'units', 'category', 'product.category']);
        }

        // 處理移除的圖片
        $removedImages = collect($images)->where('status', 'removed');
        foreach ($removedImages as $imgObj) {
            $uuid = $imgObj['uuid'] ?? null;
            if (!$uuid) {
                continue;
            }

            // 移除關聯
            $item->images()->detach($uuid);

            // 更新圖片使用次數和狀態
            $this->decrementImageUsage($uuid);
        }

        // 處理新增的圖片
        $newImages = collect($images)->where('status', 'new');
        $loopIndex = 0;
        foreach ($newImages as $imgObj) {
            $uuid = $imgObj['uuid'] ?? null;
            if (!$uuid) {
                continue;
            }

            // 避免重複 attach（使用查詢而非載入關聯）
            if (!$item->images()->where('uuid', $uuid)->exists()) {
                $item->images()->attach($uuid, [
                    'sort_order' => ++$loopIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 更新圖片使用次數和狀態
                $this->incrementImageUsage($uuid);
            }
        }

        // 原始圖片（status === 'original'）不異動

        // 重新載入關聯資料以反映最新的圖片狀態
        return $item->fresh(['images', 'units', 'category', 'product.category']);
    }

    /**
     * 驗證圖片數量
     * 僅計算 new 和 original 狀態的圖片
     *
     * @param array $images 圖片陣列
     * @param int $maxCount 最大數量
     * @return bool 是否超過最大數量
     */
    public function validateImageCount(array $images, int $maxCount = 9): bool
    {
        $activeImages = collect($images)->whereIn('status', ['new', 'original']);
        return $activeImages->count() <= $maxCount;
    }

    /**
     * 增加圖片使用次數
     *
     * @param string $uuid 圖片 UUID
     * @return void
     */
    private function incrementImageUsage(string $uuid): void
    {
        $image = $this->itemImageRepository->findByUuid($uuid);
        if ($image) {
            $this->itemImageRepository->incrementUsageCount($image);
            if ($image->status === ItemImage::STATUS_DRAFT) {
                $this->itemImageRepository->updateStatus($image, ItemImage::STATUS_USED);
            }
        }
    }

    /**
     * 減少圖片使用次數
     *
     * @param string $uuid 圖片 UUID
     * @return void
     */
    private function decrementImageUsage(string $uuid): void
    {
        $image = $this->itemImageRepository->findByUuid($uuid);
        if ($image) {
            $this->itemImageRepository->decrementUsageCount($image);
            // 直接減少物件的 usage_count，避免需要 refresh()
            $image->usage_count = max(0, $image->usage_count - 1);
            if ($image->usage_count <= 0) {
                $this->itemImageRepository->updateStatus($image, ItemImage::STATUS_DRAFT);
            }
        }
    }
}
