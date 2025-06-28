<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;

class ProductService
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    public function deleteIfNoItems(Product $product): bool
    {
        return $this->productRepository->deleteIfNoItems($product);
    }
}
