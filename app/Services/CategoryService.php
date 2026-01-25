<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class CategoryService
{
    public function __construct(private CategoryRepositoryInterface $categoryRepository)
    {
    }

    /**
     * 取得分類詳情（含統計資料和產品列表）
     *
     * @param Category $category 分類實例
     * @param int $page 產品列表頁碼
     * @param int $perPage 每頁產品筆數
     * @return array{
     *   category: \App\Models\Category,
     *   stats: array{
     *     products_count: int,
     *     items_count: int,
     *     items_in_use: int,
     *     items_unused: int,
     *     items_pre_arrival: int,
     *     items_discarded: int
     *   },
     *   products: array<int, array{
     *     id: int,
     *     short_id: string,
     *     name: string,
     *     brand: string|null,
     *     items_count: int,
     *     status_counts: array
     *   }>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function getCategoryWithStats(Category $category, int $page = 1, int $perPage = 10): array
    {
        $userId = $category->user_id;

        // 調用 Repository 取得資料
        $data = $this->categoryRepository->getCategoryWithRelations($category, $page, $perPage);

        $allItems = $data['all_items'];
        $allProducts = $data['all_products'];
        $products = $data['products'];

        // 業務邏輯：計算統計（狀態計算）
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

        // 業務邏輯：格式化產品數據
        // 從 Repository 返回的資料中取得物品（已過濾 user_id）
        $formattedProducts = $products->map(function ($product) {
            // 從已載入的關聯中取得該產品的物品（Repository 已過濾 user_id）
            $items = $product->items;

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
            'category' => $data['category'],
            'stats' => $stats,
            'products' => $formattedProducts->toArray(),
            'meta' => $data['meta'],
        ];
    }

    /**
     * 刪除分類
     *
     * @param Category $category 分類實例
     * @return bool 是否刪除成功
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 當分類仍有關聯產品時拋出
     */
    public function delete(Category $category): bool
    {
        $userId = $category->user_id;

        // 檢查是否有產品關聯此分類
        $productsCount = $this->categoryRepository->getProductsCount($category->id, $userId);
        if ($productsCount > 0) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "無法刪除此分類，因為還有 {$productsCount} 個產品關聯此分類。"
            );
        }

        return $this->categoryRepository->delete($category);
    }
}
