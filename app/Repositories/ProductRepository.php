<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    public function paginateForUser(int $userId, ?string $search, int $perPage = 10): LengthAwarePaginator
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query = Product::with(['category', 'latestOwnedItem.images'])
            ->withCount([
                'items',
                'items as discarded_items_count' => function ($query) {
                    $query->whereNotNull('discarded_at');
                },
                'items as owned_items_count' => function ($query) {
                    $query->whereNull('discarded_at');
                },
            ])
            ->where('user_id', $userId);

        if ($search) {
            $query->where(function ($q) use ($operator, $search) {
                $like = "%$search%";

                $q->where('name', $operator, $like)
                    ->orWhere('brand', $operator, $like)
                    ->orWhere('model', $operator, $like)
                    ->orWhere('spec', $operator, $like);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findByShortIdOrFail(string $shortId): Product
    {
        return Product::where('short_id', $shortId)->firstOrFail();
    }

    public function update(Product $product, array $validated): Product
    {
        $product->update($validated);

        return $product;
    }

    public function deleteIfNoItems(Product $product): bool
    {
        if ($product->items()->exists()) {
            return false;
        }
        return (bool) $product->delete();
    }
}
