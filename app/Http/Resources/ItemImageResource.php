<?php

namespace App\Http\Resources;

use App\Helpers\ImageUrlHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 圖片資源轉換
 *
 * @mixin \App\Models\ItemImage
 */
class ItemImageResource extends JsonResource
{
    /**
     * 轉換資源為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\ItemImage $itemImage */
        $itemImage = $this->resource;
        $urls = ImageUrlHelper::generateSignedUrls($itemImage, config('images.url_expiration_minutes.upload', 10));

        return [
            'uuid' => $this->uuid,
            'image_path' => $this->image_path,
            'original_extension' => $this->original_extension,
            'status' => $this->status,
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'original_path' => "item-images/{$this->uuid}/original_{$this->image_path}.{$this->original_extension}",
            'preview_path' => "item-images/{$this->uuid}/preview_{$this->image_path}.webp",
            'thumb_path' => "item-images/{$this->uuid}/thumb_{$this->image_path}.webp",
            'original_url' => $urls['original_url'],
            'preview_url' => $urls['preview_url'],
            'thumb_url' => $urls['thumb_url'],
        ];
    }
}
