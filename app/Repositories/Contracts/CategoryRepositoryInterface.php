<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * 分類資料存取介面
 * 定義 Category 資料存取的方法契約
 */
interface CategoryRepositoryInterface
{
    /**
     * 取得用戶的所有分類（帶快取）
     *
     * @param int $userId 用戶 ID
     * @return \Illuminate\Support\Collection<int, \App\Models\Category>
     */
    public function getAll(int $userId): Collection;

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
    ): array;

    /**
     * 取得分類詳情（含關聯資料）
     *
     * @param Category $category 分類實例
     * @param int $page 產品列表頁碼
     * @param int $perPage 每頁產品筆數
     * @return array{
     *   category: \App\Models\Category,
     *   products: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product>,
     *   all_products: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product>,
     *   all_items: \Illuminate\Support\Collection<int, \App\Models\Item>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function getCategoryWithRelations(
        Category $category,
        int $page = 1,
        int $perPage = 10
    ): array;

    /**
     * 取得分類的產品數量（用於刪除前檢查）
     *
     * @param int $categoryId 分類 ID
     * @param int $userId 用戶 ID
     * @return int 產品數量
     */
    public function getProductsCount(int $categoryId, int $userId): int;

    /**
     * 建立分類
     *
     * @param array<string, mixed> $data 分類資料
     * @param int $userId 用戶 ID
     */
    public function create(array $data, int $userId): Category;

    /**
     * 根據 ID 查詢分類（找不到時拋出異常）
     *
     * @param int $id 分類 ID
     * @param int $userId 用戶 ID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id, int $userId): Category;

    /**
     * 更新分類
     *
     * @param Category $category 分類實例
     * @param array<string, mixed> $data 更新資料
     */
    public function update(Category $category, array $data): Category;

    /**
     * 刪除分類
     *
     * @param Category $category 分類實例
     * @return bool 是否刪除成功
     */
    public function delete(Category $category): bool;

    /**
     * 清除用戶的分類快取
     *
     * @param int $userId 用戶 ID
     */
    public function clearCache(int $userId): void;
}
