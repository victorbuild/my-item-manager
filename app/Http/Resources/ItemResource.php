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

            'discard_note' => $this->discard_note,

            // 狀態與關聯
            'is_discarded' => $this->is_discarded,
            'category' => $this->category,
            'units' => $this->units,

            // 圖片
            'images' => $this->images->map(function ($img) {
                return [
                    'id' => $img->id,
                    'preview_url' => Storage::disk('local')->url("item-images/{$this->uuid}/preview/{$img->image_path}.webp"),
                ];
            }),

            // 系統時間
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
