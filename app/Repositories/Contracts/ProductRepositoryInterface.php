<?php

namespace App\Repositories\Contracts;

use App\Models\Product;

/**
 * 產品資料存取介面
 * 定義 Product 資料存取的方法契約
 */
interface ProductRepositoryInterface
{
    /**
     * 建立產品
     *
     * @param array<string, mixed> $validated 驗證後的資料
     * @param int $userId 使用者 ID
     * @return Product 建立後的產品
     */
    public function create(array $validated, int $userId): Product;

    /**
     * 依 short_id 取得產品，找不到則拋出例外
     *
     * @param string $shortId 產品短 ID
     * @return Product 產品
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 找不到產品時拋出
     */
    public function findByShortIdOrFail(string $shortId): Product;

    /**
     * 更新產品資料
     *
     * @param Product $product 產品
     * @param array<string, mixed> $validated 驗證後的資料
     * @return Product 更新後的產品
     */
    public function update(Product $product, array $validated): Product;

    /**
     * 若產品沒有關聯的物品，則刪除產品
     *
     * @param Product $product 產品實例
     * @return bool 若產品有關聯物品返回 false（不刪除），否則返回刪除結果
     */
    public function deleteIfNoItems(Product $product): bool;
}
