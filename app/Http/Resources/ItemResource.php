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
            'quantity' => $this->quantity,
            'price' => $this->price,
            'purchased_at' => $this->purchased_at,
            'barcode' => $this->barcode,
            'discarded_at' => $this->discarded_at,
            'is_discarded' => $this->is_discarded,
            'images' => $this->images->map(function ($img)  {
                return [
                    'id' => $img->id,
                    'preview_url' => Storage::disk('local')->url("item-images/$this->uuid/preview/$img->image_path.webp")
                ];
            }),
            'category' => $this->category,
            'units' => $this->units,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
