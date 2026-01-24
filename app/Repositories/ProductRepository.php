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

    /**
     * 依 short_id 取得產品，找不到則拋出例外
     *
     * @param string $shortId 產品短 ID
     * @return Product 產品
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 找不到產品時拋出
     */
    public function findByShortIdOrFail(string $shortId): Product
    {
        return Product::where('short_id', $shortId)->firstOrFail();
    }

    public function update(Product $product, array $validated): Product
    {
        $product->update($validated);

        return $product;
    }

    /**
     * 若產品沒有關聯的物品，則刪除產品
     *
     * @param Product $product 產品
     * @return bool 若產品有關聯物品返回 false（不刪除），否則返回刪除結果
     */
    public function deleteIfNoItems(Product $product): bool
    {
        if ($product->items()->exists()) {
            return false;
        }
        return (bool) $product->delete();
    }
}
