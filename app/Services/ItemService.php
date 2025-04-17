<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class ItemService
{
    public function create(array $data): Item
    {
        $item = Item::create($data);
        $item->user_id = auth()->id();
        $item->save();

        return $item;
    }

    public function paginateWithFilters(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Item::with(['images', 'units', 'product']);

        // 搜尋關鍵字
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // 分類篩選
        if (array_key_exists('category_id', $filters)) {
            $categoryId = $filters['category_id'];

            if ($categoryId === 'none') {
                $query->withWhereHas('product', function ($q) use ($categoryId) {
                    $q->whereNull('category_id');
                });
            } elseif ($categoryId) {
                $query->withWhereHas('product', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);

                });
            }
        }

        $paginated = $query->orderByDesc('id')->paginate($perPage);

        // 圖片網址加工
        $paginated->getCollection()->transform(function ($item) {
            $item->images->transform(function ($image) use ($item) {
                $uuid = $item->uuid;
                $filename = $image->image_path;
                $image->thumb_url = Storage::disk('local')->url("item-images/$uuid/thumb/$filename.webp");
                return $image;
            });
            return $item;
        });

        return $paginated;
    }

    public function findByShortIdOrFail(string $shortId): Item
    {
        return Item::with(['images', 'units', 'category'])
            ->where('short_id', $shortId)
            ->firstOrFail();
    }

    public function delete(Item $item): void
    {
        $item->delete();
    }
}
