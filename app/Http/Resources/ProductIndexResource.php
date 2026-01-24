<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 產品列表資源（精簡版）
 *
 * @mixin Product
 */
class ProductIndexResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestOwnedItem = $this->latestOwnedItem;

        return [
            'id' => $this->id,
            'short_id' => $this->short_id,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'name' => $this->category?->name,
                ];
            }),
            'owned_items_count' => $this->owned_items_count,
            'latest_owned_item' => $latestOwnedItem ? [
                'short_id' => $latestOwnedItem->short_id,
                'serial_number' => $latestOwnedItem->serial_number,
                'main_image' => $latestOwnedItem->images->isNotEmpty()
                    ? new ItemImageResource($latestOwnedItem->images->first())
                    : null,
            ] : null,
        ];
    }
}
