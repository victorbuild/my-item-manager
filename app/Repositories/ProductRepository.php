<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Str;

class ProductRepository implements ProductRepositoryInterface
{
    public function create(array $validated, int $userId): Product
    {
        return Product::create([
            ...$validated,
            'user_id' => $userId,
            'uuid' => (string) Str::uuid(),
            'short_id' => Str::random(8),
        ]);
    }

    public function deleteIfNoItems(Product $product): bool
    {
        if ($product->items()->exists()) {
            return false;
        }
        return (bool) $product->delete();
    }
}
