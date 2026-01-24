<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

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

        // 格式化 products，確保 latest_owned_item 使用 ItemResource
        $formattedProducts = $products->getCollection()->map(function ($product) {
            $productArr = $product->toArray();
            // 確保 latest_owned_item 使用 ItemResource，包含 main_image
            if ($product->latestOwnedItem) {
                // 確保 images 關聯已載入
                if (!$product->latestOwnedItem->relationLoaded('images')) {
                    $product->latestOwnedItem->load('images');
                }
                $productArr['latest_owned_item'] = (new ItemResource($product->latestOwnedItem))->toArray(request());
            }
            return $productArr;
        })->values();

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'items' => $formattedProducts,
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $product = $this->productRepository->create($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '成功建立產品',
            'data' => new ProductResource($product),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, string $shortId): JsonResponse
    {
        $product = Product::where('short_id', $shortId)->firstOrFail();

        $this->authorize('update', $product);

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

        $this->authorize('view', $product);

        // 使用 load 同時載入 category 和 items（包含 user_id 過濾和 images eager load）
        // 這樣可以減少查詢次數，避免 N+1 問題
        $product->load([
            'category',
            'items' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->with('images');
            },
        ]);

        $items = $product->items;

        // 狀態數量統計（直接從 Collection 計算，使用 Item 的 status 屬性）
        $statuses = ItemStatus::values();
        $statusCounts = collect($statuses)->mapWithKeys(function ($status) use ($items) {
            return [$status => $items->filter(function ($item) use ($status) {
                return $item->status === $status;
            })->count()];
        });

        // 依照 status 欄位排序 items（順序：未到貨、未使用、使用中、未使用就棄用、使用後棄用）
        $statusOrder = [
            ItemStatus::PRE_ARRIVAL->value => 0,
            ItemStatus::UNUSED->value => 1,
            ItemStatus::IN_USE->value => 2,
            ItemStatus::UNUSED_DISCARDED->value => 3,
            ItemStatus::USED_DISCARDED->value => 4,
        ];
        $sortedItems = $items->sortBy(function ($item) use ($statusOrder) {
            return $statusOrder[$item->status] ?? 5;
        })->values();

        // 使用 ItemResource 格式化 items，確保包含 main_image 等欄位
        $formattedItems = $items->map(function ($item) {
            return (new ItemResource($item))->toArray(request());
        })->values()->all();

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

    public function destroy(Request $request, string $shortId): JsonResponse
    {
        $product = Product::where('short_id', $shortId)->firstOrFail();

        $this->authorize('delete', $product);

        if (!$this->productService->deleteIfNoItems($product)) {
            return response()->json([
                'success' => false,
                'message' => '此產品仍有關聯物品，無法刪除'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => '產品已刪除'
        ]);
    }
}
