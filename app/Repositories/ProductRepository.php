<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function deleteIfNoItems(Product $product): bool
    {
        if ($product->items()->exists()) {
            return false;
        }
        return (bool) $product->delete();
    }
}
