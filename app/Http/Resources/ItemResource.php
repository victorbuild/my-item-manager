<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'short_id' => $this->short_id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'price' => $this->price,
            'barcode' => $this->barcode,
            'serial_number' => $this->serial_number,
            'notes' => $this->notes,

            // 時間欄位
            'purchased_at' => $this->purchased_at,
            'received_at' => $this->received_at,
            'used_at' => $this->used_at,
            'discarded_at' => $this->discarded_at,
            'expiration_date' => optional($this->expiration_date)->toDateString(),

            'discard_note' => $this->discard_note,

            // 狀態與關聯
            'is_discarded' => $this->is_discarded,
            'status' => $this->status,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'short_id' => $this->product->short_id,
                    'name' => $this->product->name,
                    'barcode' => $this->product->barcode,
                    'category' => [
                        'id' => $this->product->category?->id,
                        'name' => $this->product->category?->name,
                    ],
                ];
            }),
            'category' => $this->category,
            'units' => $this->units,

            // 圖片
            'images' => $this->images->map(function ($img) {
                $previewPath = "item-images/{$this->uuid}/preview/{$img->image_path}.webp";
                $thumbPath = "item-images/{$this->uuid}/thumb/{$img->image_path}.webp";

                return [
                    'id' => $img->id,
                    'path' => $previewPath,
                    'preview_url' => Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(60)),
                    'thumb_url' => Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60)),
                ];
            }),

            // 系統時間
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
