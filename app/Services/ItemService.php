<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
        $query = Item::with(['images', 'units', 'product.category']);

        // 搜尋關鍵字
        if (!empty($filters['search'])) {
            $query->where('name', 'ILIKE', '%' . $filters['search'] . '%');
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

        // 狀態多選篩選
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['statuses'] as $status) {
                    $q->orWhere(function ($sub) use ($status) {
                        if ($status === 'pre_arrival') {
                            // 未到貨：discarded_at 為空 且 used_at 為空 且 received_at 為空
                            $sub->whereNull('discarded_at')->whereNull('used_at')->whereNull('received_at');
                        } elseif ($status === 'unused') {
                            // 未使用：received_at 有值 且 used_at 為空 且 discarded_at 為空
                            $sub->whereNotNull('received_at')->whereNull('used_at')->whereNull('discarded_at');
                        } elseif ($status === 'in_use') {
                            // 使用中：used_at 有值 且 discarded_at 為空
                            $sub->whereNotNull('used_at')->whereNull('discarded_at');
                        } elseif ($status === 'unused_discarded') {
                            // 未使用就棄用：discarded_at 有值 且 used_at 為空
                            $sub->whereNotNull('discarded_at')->whereNull('used_at');
                        } elseif ($status === 'used_discarded') {
                            // 使用後棄用：discarded_at 有值 且 used_at 有值
                            $sub->whereNotNull('discarded_at')->whereNotNull('used_at');
                        }
                    });
                }
            });
        }

        // 根據排序模式決定排序方式
        $sortMode = $filters['sort'] ?? 'default';

        if ($sortMode === 'discarded') {
            // 棄用排序：按棄用時間降序
            $query->orderByDesc('discarded_at');
        } else {
            // 預設排序：按 ID 降序
            $query->orderByDesc('id');
        }

        $paginated = $query->paginate($perPage);

        // 圖片網址加工
        $paginated->getCollection()->transform(function ($item) {
            $item->images->transform(function ($image) {
                $filename = $image->image_path;
                $image->thumb_url = Storage::disk('local')->url("item-images/{$image->uuid}/thumb_{$filename}.webp");
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
        DB::transaction(function () use ($item) {
            foreach ($item->images as $image) {
                $item->images()->detach($image->uuid);
                $image->decrement('usage_count');

                if ($image->usage_count <= 0) {
                    $image->status = 'draft';
                    $image->save();
                }
            }

            $item->delete();
        });
    }
}
