<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function deleteIfNoItems(Product $product): bool
    {
        if ($product->items()->exists()) {
            return false;
        }
        return (bool) $product->delete();
    }
}
