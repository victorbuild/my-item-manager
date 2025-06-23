<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

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
                    'images' => $item->images->take(4)->map(function ($img) {
                        return [
                            'uuid' => $img->uuid,
                            'thumb_url' => $img->thumb_url,
                        ];
                    }),
                ];
            }),
        ];
    }
}
