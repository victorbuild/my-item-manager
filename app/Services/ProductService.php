<?php

namespace App\Services;

use App\Exceptions\UnprocessableEntityException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductService
{
    public function __construct(private readonly ProductRepositoryInterface $productRepository)
    {
    }

    /**
     * 刪除產品（若產品仍有關聯物品則拋出 422）
     *
     * @param Product $product
     * @return void
     * @throws UnprocessableEntityException 當產品仍有關聯物品時拋出
     */
    public function deleteOrFailIfNoItems(Product $product): void
    {
        if (!$this->productRepository->deleteIfNoItems($product)) {
            throw new UnprocessableEntityException('此產品仍有關聯物品，無法刪除');
        }
    }

    public function deleteIfNoItems(Product $product): bool
    {
        return $this->productRepository->deleteIfNoItems($product);
    }
}
