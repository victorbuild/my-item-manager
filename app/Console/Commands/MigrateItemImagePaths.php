<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateItemImagePaths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:migrate-path-to-uuid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate item_images.image_path from numeric ID folder to UUID folder and update DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('public');

        $images = ItemImage::all();

        foreach ($images as $image) {
            $oldPath = $image->image_path;

            // 解析舊路徑中的 ID
            $matches = [];
            if (!preg_match('#item-images/(\d+)/(.+)$#', $oldPath, $matches)) {
                $this->warn("Skipped: Invalid path format - {$oldPath}");
                continue;
            }

            $itemId = $matches[1];
            $fileName = $matches[2];

            $item = Item::find($itemId);
            if (!$item || !$item->uuid) {
                $this->warn("Skipped: Item {$itemId} not found or no UUID");
                continue;
            }

            $newPath = "item-images/{$item->uuid}/{$fileName}";

            if (!$disk->exists($oldPath)) {
                $this->warn("Skipped: File not found - {$oldPath}");
                continue;
            }

            // 確保新目錄存在
            $disk->makeDirectory("item-images/{$item->uuid}");

            // 移動檔案
            $disk->move($oldPath, $newPath);

            // 更新資料庫欄位
            $image->image_path = $newPath;
            $image->save();

            $this->info("Moved and updated DB: {$oldPath} → {$newPath}");
        }

        $this->info('✅ All image paths migrated and updated.');
        return 0;
    }
}
