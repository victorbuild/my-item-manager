<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Category $category */
        $category = $this->resource;

        $result = [
            'id' => $category->id,
            'name' => $category->name,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];

        // 動態添加的計數屬性（通過 withCount 或手動設置）
        if (isset($category->products_count)) {
            $result['products_count'] = $category->products_count;
        }

        if (isset($category->items_count)) {
            $result['items_count'] = $category->items_count;
        }

        return $result;
    }
}
