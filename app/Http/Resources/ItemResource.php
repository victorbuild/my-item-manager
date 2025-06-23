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
            'purchased_at' => optional($this->purchased_at)->format('Y-m-d'),
            'received_at' => optional($this->received_at)->format('Y-m-d'),
            'used_at' => optional($this->used_at)->format('Y-m-d'),
            'discarded_at' => optional($this->discarded_at)->format('Y-m-d'),
            'expiration_date' => optional($this->expiration_date)->format('Y-m-d'),

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
                $previewPath = "item-images/{$img->uuid}/preview_{$img->image_path}.webp";
                $thumbPath = "item-images/{$img->uuid}/thumb_{$img->image_path}.webp";

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
