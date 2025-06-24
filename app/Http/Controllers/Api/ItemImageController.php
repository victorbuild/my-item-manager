<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ItemImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json(['message' => 'No image uploaded.'], 400);
        }

        $file = $request->file('image');

        if (!$file->isValid()) {
            return response()->json(['message' => 'Uploaded file is invalid.'], 422);
        }

        try {
            $uuid = (string) \Str::uuid();
            $folderPath = "item-images/{$uuid}/";
            $basename = bin2hex(random_bytes(20)); // 產生 40 字元隨機檔名
            $extension = strtolower($file->getClientOriginalExtension());
            $originalName = "original_{$basename}." . $extension;
            $webpNamePreview = "preview_{$basename}.webp";
            $webpNameThumb = "thumb_{$basename}.webp";

            $originalPath = $folderPath . $originalName;
            $previewPath = $folderPath . $webpNamePreview;
            $thumbPath = $folderPath . $webpNameThumb;

            $fileContent = file_get_contents($file->getRealPath());
            if ($fileContent === false) {
                throw new \Exception('無法讀取檔案內容');
            }

            // 上傳原圖
            $originalUploaded = Storage::disk('gcs')->put($originalPath, $fileContent);

            // 產生縮圖與預覽圖
            $manager = new ImageManager(new Driver());
            $img = $manager->read($fileContent);
            $preview = $img->scaleDown(width: 600, height: 800)->toWebp(85);
            $thumb = $img->scaleDown(width: 300, height: 400)->toWebp(75);

            // 上傳縮圖與預覽圖
            $previewUploaded = Storage::disk('gcs')->put($previewPath, $preview);
            $thumbUploaded = Storage::disk('gcs')->put($thumbPath, $thumb);

            if (!$originalUploaded || !$previewUploaded || !$thumbUploaded) {
                throw new \Exception('圖片上傳失敗');
            }

            // 儲存資料至資料庫
            $itemImage = ItemImage::create([
                'uuid' => $uuid,
                'image_path' => $basename,
                'original_extension' => $extension,
                'status' => 'draft',
                'usage_count' => 0,
            ]);

            if (!$itemImage) {
                throw new \Exception('資料庫寫入失敗');
            }

            // 取得簽署網址
            $signedUrl = Storage::disk('gcs')->temporaryUrl($originalPath, now()->addMinutes(10));
            $previewUrl = Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(10));
            $thumbUrl = Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(10));

            return response()->json([
                'message' => '圖片上傳成功！',
                'uuid' => $uuid,
                'original_path' => $originalPath,
                'preview_path' => $previewPath,
                'thumb_path' => $thumbPath,
                'original_url' => $signedUrl,
                'preview_url' => $previewUrl,
                'thumb_url' => $thumbUrl,
            ], 200);
        } catch (\Exception $e) {
            if (isset($originalPath, $previewPath, $thumbPath)) {
                Storage::disk('gcs')->delete([$originalPath, $previewPath, $thumbPath]);
            }
            \Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => '圖片上傳失敗。',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
