<?php

namespace App\Http\Resources;

use App\Models\ItemImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ItemImage
 */
class ItemImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $previewPath = "item-images/{$this->uuid}/preview_{$this->image_path}.webp";
        $thumbPath = "item-images/{$this->uuid}/thumb_{$this->image_path}.webp";

        return [
            'uuid' => $this->uuid,
            'image_path' => $this->image_path,
            'original_extension' => $this->original_extension,
            'status' => $this->status,
            'usage_count' => $this->usage_count,
            'path' => $previewPath,
            'preview_url' => Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(60)),
            'thumb_url' => Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
