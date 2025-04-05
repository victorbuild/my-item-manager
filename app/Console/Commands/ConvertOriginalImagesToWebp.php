<?php

namespace App\Console\Commands;

use App\Models\ItemImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ConvertOriginalImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:convert-to-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert existing original images to WebP format with preview and thumbnail sizes (local only)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('public');
        $manager = new ImageManager(
            new Driver()
        );

        $images = ItemImage::all();

        foreach ($images as $image) {
            $originalPath = $image->image_path; // e.g., item-images/{uuid}/{filename}.jpg

            if (!$disk->exists($originalPath)) {
                $this->warn("File not found: {$originalPath}");
                continue;
            }

            $uuid = explode('/', $originalPath)[1];
            $oldFilename = basename($originalPath);
            $baseName = pathinfo($oldFilename, PATHINFO_FILENAME);
            $newFilename = $baseName . '.webp';

            $imageData = $disk->get($originalPath);
            $img = $manager->read($imageData);

            $width = $img->width();
            $height = $img->height();

            // 原圖
            $disk->copy($originalPath, "item-images/{$uuid}/original/{$oldFilename}");

            $previewMaxWidth = 600;
            $previewMaxHeight = 800;
            $thumbMaxWidth = 300;
            $thumbMaxHeight = 400;

            if ($width >= $height) {
                // 橫圖：以寬為主
                $preview = $img->scaleDown(width: $previewMaxWidth, height: $previewMaxHeight)->toWebp(85);
                $thumb = $img->scaleDown(width: $thumbMaxWidth, height: $thumbMaxHeight)->toWebp(75);
            } else {
                // 直圖：以高為主
                $preview = $img->scaleDown(width: $previewMaxWidth, height: $previewMaxHeight)->toWebp(85);
                $thumb = $img->scaleDown(width: $thumbMaxWidth, height: $thumbMaxHeight)->toWebp(75);
            }

            $disk->put("item-images/{$uuid}/preview/{$newFilename}", $preview);
            $disk->put("item-images/{$uuid}/thumb/{$newFilename}", $thumb);

            // 更新資料庫欄位（假設你已經有 image_filename 欄位）
            $image->image_path = $newFilename;
            $image->save();

            $this->info("Converted {$originalPath} to {$newFilename} in local storage (original kept as-is)");
        }

        $this->info('✅ All images converted to WebP locally.');
    }
}
