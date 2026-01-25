<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * 物品圖片服務
 * 負責處理物品與圖片的關聯、圖片狀態更新、圖片上傳等業務邏輯
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
            return $item->fresh(['images', 'category', 'product.category']);
        }

        // 處理移除的圖片
        $removedImages = collect($images)->where('status', 'removed');
        foreach ($removedImages as $imgObj) {
            $uuid = $imgObj['uuid'] ?? null;
            if (! $uuid) {
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
            if (! $uuid) {
                continue;
            }

            // 避免重複 attach（使用查詢而非載入關聯）
            if (! $item->images()->where('uuid', $uuid)->exists()) {
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
        return $item->fresh(['images', 'category', 'product.category']);
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

    /**
     * 上傳圖片並產生縮圖
     *
     * @param \Illuminate\Http\UploadedFile $file 上傳的檔案
     * @param int $userId 用戶 ID
     * @return array{uuid: string, original_path: string, preview_path: string, thumb_path: string}
     *
     * @throws \Exception
     */
    public function uploadImage(UploadedFile $file, int $userId): array
    {
        // 產生 UUID 和檔案名稱
        $uuid = (string) \Str::uuid();
        $basename = bin2hex(random_bytes(20)); // 產生 40 字元隨機檔名
        $extension = strtolower($file->getClientOriginalExtension());

        // 定義路徑
        $folderPath = "item-images/{$uuid}/";
        $originalName = "original_{$basename}.{$extension}";
        $webpNamePreview = "preview_{$basename}.webp";
        $webpNameThumb = "thumb_{$basename}.webp";

        $originalPath = $folderPath . $originalName;
        $previewPath = $folderPath . $webpNamePreview;
        $thumbPath = $folderPath . $webpNameThumb;

        // 讀取檔案內容
        $fileContent = file_get_contents($file->getRealPath());
        if ($fileContent === false) {
            throw new \Exception('無法讀取檔案內容');
        }

        try {
            // 上傳原圖
            $originalUploaded = Storage::disk('gcs')->put($originalPath, $fileContent);
            if (! $originalUploaded) {
                throw new \Exception('原圖上傳失敗');
            }

            // 產生縮圖與預覽圖
            $manager = new ImageManager(new Driver());
            $img = $manager->read($fileContent);

            $preview = $img->scaleDown(
                width: config('images.preview.width', 600),
                height: config('images.preview.height', 800)
            )->toWebp(config('images.preview.quality', 85));

            $thumb = $img->scaleDown(
                width: config('images.thumb.width', 300),
                height: config('images.thumb.height', 400)
            )->toWebp(config('images.thumb.quality', 75));

            // 上傳縮圖與預覽圖
            $previewUploaded = Storage::disk('gcs')->put($previewPath, $preview);
            $thumbUploaded = Storage::disk('gcs')->put($thumbPath, $thumb);

            if (! $previewUploaded || ! $thumbUploaded) {
                // 清理已上傳的檔案
                Storage::disk('gcs')->delete([$originalPath, $previewPath, $thumbPath]);
                throw new \Exception('縮圖上傳失敗');
            }

            // 儲存資料至資料庫
            $this->itemImageRepository->create([
                'uuid' => $uuid,
                'image_path' => $basename,
                'original_extension' => $extension,
                'status' => ItemImage::STATUS_DRAFT,
                'usage_count' => 0,
                'user_id' => $userId,
            ]);

            return [
                'uuid' => $uuid,
                'original_path' => $originalPath,
                'preview_path' => $previewPath,
                'thumb_path' => $thumbPath,
            ];
        } catch (\Exception $e) {
            // 確保清理已上傳的檔案
            Storage::disk('gcs')->delete([$originalPath, $previewPath, $thumbPath]);
            throw $e;
        }
    }
}
