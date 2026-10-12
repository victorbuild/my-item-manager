<?php

namespace App\Helpers;

use App\Models\ItemImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * 圖片 URL 生成輔助類別
 * 提供統一的圖片 URL 生成方法
 */
class ImageUrlHelper
{
    private const VARIANTS = ['original', 'preview', 'thumb'];

    /**
     * 產生圖片的簽署 URL
     *
     * @param \App\Models\ItemImage $image 圖片實例
     * @param int $expirationMinutes 過期時間（分鐘），預設為一般查詢的過期時間
     * @param array<int, string> $variants 需要產生的圖片尺寸
     * @return array<string, string>
     */
    public static function generateSignedUrls(
        ItemImage $image,
        ?int $expirationMinutes = null,
        array $variants = self::VARIANTS
    ): array {
        if ($expirationMinutes === null) {
            $expirationMinutes = config('images.url_expiration_minutes.default', 60);
        }

        $paths = self::paths($image);
        $expiresAt = now()->addMinutes($expirationMinutes);
        $cacheMinutes = max(
            1,
            $expirationMinutes - config('images.url_cache_safety_minutes', 5)
        );
        $urls = [];

        foreach (array_unique($variants) as $variant) {
            if (! in_array($variant, self::VARIANTS, true)) {
                continue;
            }

            $path = $paths[$variant];
            $cacheKey = self::cacheKey($image, $variant, $path);
            $urls["{$variant}_url"] = Cache::remember(
                $cacheKey,
                now()->addMinutes($cacheMinutes),
                fn (): string => Storage::disk('gcs')->temporaryUrl(
                    $path,
                    $expiresAt,
                    ['version' => 'v4']
                )
            );
        }

        return $urls;
    }

    /**
     * 清除圖片的簽署 URL 快取。
     */
    public static function forgetSignedUrls(ItemImage $image): void
    {
        foreach (self::paths($image) as $variant => $path) {
            Cache::forget(self::cacheKey($image, $variant, $path));
        }
    }

    /**
     * @return array{original: string, preview: string, thumb: string}
     */
    private static function paths(ItemImage $image): array
    {
        $folder = "item-images/{$image->uuid}";

        return [
            'original' => "{$folder}/original_{$image->image_path}.{$image->original_extension}",
            'preview' => "{$folder}/preview_{$image->image_path}.webp",
            'thumb' => "{$folder}/thumb_{$image->image_path}.webp",
        ];
    }

    private static function cacheKey(ItemImage $image, string $variant, string $path): string
    {
        return "image-signed-url:{$image->uuid}:{$variant}:" . sha1($path);
    }
}
