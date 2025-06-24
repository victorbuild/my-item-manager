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

        // 依照 status 欄位排序 items
        $statusOrder = [
            'in_use' => 0,
            'stored' => 1,
            'pre_arrival' => 2,
            'used_and_gone' => 3,
            'unused_but_gone' => 3,
        ];
        $sortedItems = $product->items->sortBy(function ($item) use ($statusOrder) {
            return $statusOrder[$item->status] ?? 4;
        })->values();

        // 格式化 items 的日期欄位為 Y-m-d（產生 array，不用 Model 實體）
        $dateFields = ['purchased_at', 'received_at', 'used_at', 'discarded_at', 'expiration_date'];
        $formattedItems = $product->items->map(function ($item) use ($dateFields) {
            $arr = $item->toArray();
            foreach ($dateFields as $field) {
                if (!empty($arr[$field])) {
                    $arr[$field] = \Carbon\Carbon::parse($arr[$field])
                        ->setTimezone(config('app.timezone'))
                        ->format('Y-m-d');
                }
            }
            return $arr;
        })->values()->all();

        // 狀態數量統計
        $statuses = ['in_use', 'stored', 'pre_arrival', 'used_and_gone', 'unused_but_gone'];
        $statusCounts = collect($statuses)->mapWithKeys(function ($status) use ($formattedItems) {
            return [$status => collect($formattedItems)->where('status', $status)->count()];
        });

        // 回傳時直接覆蓋 items 並加上 items_count
        $productArr = $product->toArray();
        $productArr['items'] = $formattedItems;
        $productArr['items_count'] = count($formattedItems);
        $productArr['status_counts'] = $statusCounts;

        return response()->json([
            'success' => true,
            'item' => $productArr,
        ]);
    }
}
