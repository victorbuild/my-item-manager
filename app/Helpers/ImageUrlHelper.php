<?php

namespace App\Helpers;

use App\Models\ItemImage;
use Illuminate\Support\Facades\Storage;

/**
 * 圖片 URL 生成輔助類別
 * 提供統一的圖片 URL 生成方法
 */
class ImageUrlHelper
{
    /**
     * 產生圖片的簽署 URL
     *
     * @param \App\Models\ItemImage $image 圖片實例
     * @param int $expirationMinutes 過期時間（分鐘），預設為上傳時的過期時間
     * @return array{original_url: string, preview_url: string, thumb_url: string}
     */
    public static function generateSignedUrls(ItemImage $image, ?int $expirationMinutes = null): array
    {
        if ($expirationMinutes === null) {
            $expirationMinutes = config('images.url_expiration_minutes.upload', 10);
        }

        $uuid = $image->uuid;
        $basename = $image->image_path;
        $extension = $image->original_extension;

        $originalPath = "item-images/{$uuid}/original_{$basename}.{$extension}";
        $previewPath = "item-images/{$uuid}/preview_{$basename}.webp";
        $thumbPath = "item-images/{$uuid}/thumb_{$basename}.webp";

        return [
            'original_url' => Storage::disk('gcs')->temporaryUrl(
                $originalPath,
                now()->addMinutes($expirationMinutes)
            ),
            'preview_url' => Storage::disk('gcs')->temporaryUrl(
                $previewPath,
                now()->addMinutes($expirationMinutes)
            ),
            'thumb_url' => Storage::disk('gcs')->temporaryUrl(
                $thumbPath,
                now()->addMinutes($expirationMinutes)
            ),
        ];
    }
}
