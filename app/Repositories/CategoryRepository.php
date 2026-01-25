<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Item;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * 取得用戶的所有分類（帶快取）
     */
    public function getAll(int $userId): Collection
    {
        $cacheKey = "categories:user:{$userId}";
        $cacheTTL = 3600; // 1 小時

        return Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
            return Category::where('user_id', $userId)
                ->select(['id', 'name', 'uuid'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * 清除用戶的分類快取
     */
    public function clearCache(int $userId): void
    {
        $cacheKey = "categories:user:{$userId}";
        Cache::forget($cacheKey);
    }

    public function create(array $data, int $userId): Category
    {
        $category = Category::create([
            'name' => $data['name'],
            'user_id' => $userId,
        ]);

        // 清除快取
        $this->clearCache($userId);

        return $category;
    }

    public function findOrFail(int $id, int $userId): Category
    {
        return Category::where('user_id', $userId)
            ->findOrFail($id);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        // 清除快取
        $this->clearCache($category->user_id);

        return $category->fresh();
    }

    public function delete(Category $category): bool
    {
        $userId = $category->user_id;
        $result = $category->delete();

        // 清除快取
        $this->clearCache($userId);

        return $result;
    }

    /**
     * 取得分頁的分類列表（含產品和物品數量統計）
     *
     * @param int $userId 用戶 ID
     * @param int $page 頁碼
     * @param int $perPage 每頁筆數
     * @param string|null $search 搜尋關鍵字
     * @return array{
     *   items: \Illuminate\Support\Collection<int, \App\Models\Category>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function getAllPaginatedWithCounts(
        int $userId,
        int $page = 1,
        int $perPage = 10,
        ?string $search = null
    ): array {
        // 取得所有分類（帶快取）
        $categories = $this->getAll($userId);

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
        $lastPage = (int) ceil($total / $perPage);

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

    /**
     * 取得分類詳情（含關聯資料）
     *
     * @param Category $category 分類實例
     * @param int $page 產品列表頁碼
     * @param int $perPage 每頁產品筆數
     * @return array{
     *   category: \App\Models\Category,
     *   products: \Illuminate\Support\Collection<int, \App\Models\Product>,
     *   all_products: \Illuminate\Support\Collection<int, \App\Models\Product>,
     *   all_items: \Illuminate\Support\Collection<int, \App\Models\Item>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function getCategoryWithRelations(
        Category $category,
        int $page = 1,
        int $perPage = 10
    ): array {
        $userId = $category->user_id;

        // 載入關聯
        $category->load('products.items');

        // 獲取所有產品（用於統計）
        $allProducts = $category->products()->where('user_id', $userId)->get();

        // 取得所有物品
        $allItems = collect();
        foreach ($allProducts as $product) {
            $productItems = $product->items()->where('user_id', $userId)->get();
            $allItems = $allItems->concat($productItems);
        }

        // 分頁產品
        $totalProducts = $allProducts->count();
        $products = $allProducts->slice(($page - 1) * $perPage, $perPage)->values();
        $lastPage = (int) ceil($totalProducts / $perPage);

        return [
            'category' => $category,
            'products' => $products,
            'all_products' => $allProducts,
            'all_items' => $allItems,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalProducts,
            ],
        ];
    }

    /**
     * 取得分類的產品數量（用於刪除前檢查）
     *
     * @param int $categoryId 分類 ID
     * @param int $userId 用戶 ID
     * @return int 產品數量
     */
    public function getProductsCount(int $categoryId, int $userId): int
    {
        return Category::find($categoryId)
            ->products()
            ->where('user_id', $userId)
            ->count();
    }
}
