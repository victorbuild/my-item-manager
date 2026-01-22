<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Storage;

class ItemCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
            ],
            'items' => $this->collection->transform(function ($item) {
                $formatDate = function ($date) {
                    return $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
                };
                return [
                    'name' => $item->name,
                    'price' => $item->price,
                    'purchased_at' => $formatDate($item->purchased_at),
                    'received_at' => $formatDate($item->received_at),
                    'used_at' => $formatDate($item->used_at),
                    'discarded_at' => $formatDate($item->discarded_at),
                    'serial_number' => $item->serial_number,
                    'uuid' => $item->uuid,
                    'short_id' => $item->short_id,
                    'expiration_date' => $formatDate($item->expiration_date),
                    'status' => $item->status,
                    // 主圖（第一張圖片）
                    'main_image' => $item->images->isNotEmpty() ? (function ($img) {
                        $thumbPath = "item-images/{$img->uuid}/thumb_{$img->image_path}.webp";
                        return [
                            'thumb_url' => Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60)),
                        ];
                    })($item->images->first()) : null,
                ];
            }),
        ];
    }
}
