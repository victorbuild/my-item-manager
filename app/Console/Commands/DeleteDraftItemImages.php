<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ItemImage;
use Illuminate\Support\Facades\Storage;

class DeleteDraftItemImages extends Command
{
    protected $signature = 'item-images:delete-drafts';
    protected $description = '刪除 item_images 資料表中屬於草稿的圖片，並從 GCS 刪除原圖、預覽圖、縮圖';

    public function handle()
    {
        $deletedCount = 0;
        ItemImage::where('status', ItemImage::STATUS_DRAFT)
            ->orderBy('id', 'asc')
            ->chunk(100, function ($draftImages) use (&$deletedCount) {
                $disk = Storage::disk('gcs');
                foreach ($draftImages as $image) {
                    $itemCount = $image->items()->count();
                    if ($itemCount === 0) {
                        $uuid = $image->uuid;
                        $basename = $image->image_path;
                        $originalExt = $image->original_extension;
                        // 原圖
                        $originalPath = "item-images/{$uuid}/original_{$basename}.{$originalExt}";
                        // 預覽圖
                        $previewPath = "item-images/{$uuid}/preview_{$basename}.webp";
                        // 縮圖
                        $thumbPath = "item-images/{$uuid}/thumb_{$basename}.webp";
                        $paths = [$originalPath, $previewPath, $thumbPath];
                        foreach ($paths as $path) {
                            if ($disk->exists($path)) {
                                $disk->delete($path);
                            }
                        }
                        $image->delete();
                        $deletedCount++;
                    } else {
                        // 有關聯，改為 used 並寫入 usage_count
                        $image->status = ItemImage::STATUS_USED;
                        $image->usage_count = $itemCount;
                        $image->save();
                    }
                }
            });
        $this->info("已刪除 {$deletedCount} 筆草稿圖片及其檔案。");
    }
}
