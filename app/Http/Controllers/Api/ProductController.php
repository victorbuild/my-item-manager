<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(
            [
                'category',
                'items',
                'latestItem.images',
                'latestOwnedItem.images'
            ]
        )
        ->withCount([
            'items',
            'items as discarded_items_count' => function ($query) {
                $query->whereNotNull('discarded_at');
            },
            'items as owned_items_count' => function ($query) {
                $query->whereNull('discarded_at');
            },
        ])
            ->where('user_id', $request->user()->id);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%$search%")
                    ->orWhere('brand', 'ILIKE', "%$search%")
                    ->orWhere('model', 'ILIKE', "%$search%")
                    ->orWhere('spec', 'ILIKE', "%$search%");
            });
        }

        $products = $query
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'items' => $products->items(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'model' => 'nullable|string|max:255',
            'spec' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ]);

        $product = Product::create([
            ...$validated,
            'user_id' => $request->user()->id, // 如果有 user 綁定
            'uuid' => (string)Str::uuid(),
            'short_id' => Str::random(8),
        ]);

        return response()->json([
            'success' => true,
            'message' => '建立成功',
            'items' => [
                $product
            ],
        ]);
    }

    public function update(Request $request, string $shortId): JsonResponse
    {
        $product = Product::where('short_id', $shortId)->firstOrFail();

        // 確保使用者只能編輯自己的產品
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['message' => '無權限修改此產品'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'model' => 'nullable|string|max:255',
            'spec' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'item' => $product,
        ]);
    }

    public function show(Request $request, string $shortId): JsonResponse
    {
        $product = Product::where('short_id', $shortId)->firstOrFail();

        if ($product->user_id !== $request->user()->id) {
            return response()->json(['message' => '無權限檢視此產品'], 403);
        }

        $product->load([
            'category',
            'items.images',
        ]);

        // 依照使用狀態排序 items
        $sortedItems = $product->items->sortBy(function ($item) {
            if ($item->started_at && !$item->discarded_at) {
                return 0; // 使用中
            } elseif (!$item->started_at && !$item->discarded_at && $item->purchased_at) {
                return 1; // 擁有中
            } elseif (!$item->started_at && !$item->purchased_at && !$item->discarded_at) {
                return 2; // 未到貨
            } elseif ($item->discarded_at) {
                return 3; // 已棄用
            }
            return 4; // 其他情況（保底）
        })->values(); // 重新 index

        // 將排序後的 items 附加回產品資料
        $product->setRelation('items', $sortedItems);

        return response()->json([
            'success' => true,
            'item' => $product,
        ]);
    }
}
