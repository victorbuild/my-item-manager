<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;

readonly class CategoryService
{
    public function __construct(private CategoryRepository $categoryRepository)
    {
    }

    public function getAll(int $userId): Collection
    {
        return $this->categoryRepository->getAll($userId);
    }

    public function getAllPaginated(int $userId, int $page = 1, int $perPage = 10, ?string $search = null): array
    {
        $categories = $this->categoryRepository->getAll($userId);

        // 如果有搜索參數，先過濾
        if ($search) {
            $categories = $categories->filter(function ($category) use ($search) {
                return stripos($category->name, $search) !== false;
            });
        }

        // 為每個分類添加產品和物品數量
        $categoriesWithCounts = $categories->map(function ($category) use ($userId) {
            $category->products_count = $category->products()
                ->where('user_id', $userId)
                ->count();
            $category->items_count = Item::whereHas('product', function ($q) use ($category, $userId) {
                $q->where('category_id', $category->id)
                    ->where('user_id', $userId);
            })->where('user_id', $userId)->count();
            return $category;
        });

        // 手動分頁
        $total = $categoriesWithCounts->count();
        $items = $categoriesWithCounts->slice(($page - 1) * $perPage, $perPage)->values();
        $lastPage = (int)ceil($total / $perPage);

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    public function findOrFail(int $id, int $userId): Category
    {
        return $this->categoryRepository->findOrFail($id, $userId);
    }

    public function getCategoryWithStats(int $id, int $userId, int $page = 1, int $perPage = 10): array
    {
        $category = $this->categoryRepository->findOrFail($id, $userId);

        // 載入關聯
        $category->load('products.items');

        // 獲取所有產品（用於統計）
        $allProducts = $category->products()->where('user_id', $userId)->get();

        // 分頁產品
        $totalProducts = $allProducts->count();
        $products = $allProducts->slice(($page - 1) * $perPage, $perPage)->values();

        // 計算所有物品的統計
        /** @var \Illuminate\Support\Collection<int, \App\Models\Item> $allItems */
        $allItems = collect();
        foreach ($allProducts as $product) {
            /** @var \App\Models\Product $product */
            $productItems = $product->items()->where('user_id', $userId)->get();
            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $productItems */
            $allItems = $allItems->concat($productItems);
        }

        // 計算統計（使用 Collection 的 filter 方法配合 Item 的狀態判斷）
        $stats = [
            'products_count' => $allProducts->count(),
            'items_count' => $allItems->count(),
            'items_in_use' => $allItems->filter(function ($item) {
                return Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                ) === 'in_use';
            })->count(),
            'items_unused' => $allItems->filter(function ($item) {
                return Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                ) === 'unused';
            })->count(),
            'items_pre_arrival' => $allItems->filter(function ($item) {
                return Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                ) === 'pre_arrival';
            })->count(),
            'items_discarded' => $allItems->filter(function ($item) {
                $status = Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                );
                return in_array($status, ['unused_discarded', 'used_discarded']);
            })->count(),
        ];

        $lastPage = (int)ceil($totalProducts / $perPage);

        // 格式化產品數據
        $formattedProducts = $products->map(function ($product) use ($userId) {
            /** @var \App\Models\Product $product */
            /** @var \Illuminate\Support\Collection<int, \App\Models\Item> $items */
            $items = $product->items()->where('user_id', $userId)->get();

            // 計算每個產品的狀態統計（使用 Collection 的 filter 方法配合 Item 的狀態判斷）
            $itemsInUse = $items->filter(function ($item) {
                return Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                ) === 'in_use';
            })->count();
            $itemsUnused = $items->filter(function ($item) {
                return Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                ) === 'unused';
            })->count();
            $itemsPreArrival = $items->filter(function ($item) {
                return Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                ) === 'pre_arrival';
            })->count();
            $itemsDiscarded = $items->filter(function ($item) {
                $status = Item::getStatusFromDates(
                    $item->discarded_at,
                    $item->used_at,
                    $item->received_at
                );
                return in_array($status, ['unused_discarded', 'used_discarded']);
            })->count();

            return [
                'id' => $product->id,
                'short_id' => $product->short_id,
                'name' => $product->name,
                'brand' => $product->brand,
                'items_count' => $items->count(),
                'status_counts' => [
                    'pre_arrival' => $itemsPreArrival,
                    'unused' => $itemsUnused,
                    'in_use' => $itemsInUse,
                    'discarded' => $itemsDiscarded,
                ],
            ];
        });

        return [
            'category' => $category,
            'stats' => $stats,
            'products' => $formattedProducts,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalProducts,
            ],
        ];
    }

    public function update(int $id, array $data, int $userId): Category
    {
        $category = $this->categoryRepository->findOrFail($id, $userId);
        return $this->categoryRepository->update($category, $data);
    }

    public function delete(int $id, int $userId): bool
    {
        $category = $this->categoryRepository->findOrFail($id, $userId);

        // 檢查是否有產品關聯此分類
        $productsCount = $category->products()->where('user_id', $userId)->count();
        if ($productsCount > 0) {
            throw new \RuntimeException("無法刪除此分類，因為還有 {$productsCount} 個產品關聯此分類。");
        }

        return $this->categoryRepository->delete($category);
    }
}
