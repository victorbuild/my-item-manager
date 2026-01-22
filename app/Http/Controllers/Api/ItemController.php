<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemCollection;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemImageService;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemImageService $itemImageService
    ) {
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'statuses' => $request->filled('statuses') ? explode(',', $request->input('statuses')) : [],
            'sort' => $request->input('sort', 'default'),
        ];

        $perPage = $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 100); // 限制在 1-100 之間
        $items = $this->itemService->paginateWithFilters($filters, $perPage);
        $collection = new ItemCollection($items);
        $data = $collection->toArray($request);

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => $data['meta'],
            'items' => $data['items'],
        ]);
    }

    /**
     * 建立物品
     *
     * @param StoreItemRequest $request
     * @return JsonResponse
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $quantity = $this->calculateQuantity($validated);
        
        $createdItems = $this->createItemsWithImages($validated, $quantity);

        return response()->json([
            'success' => true,
            'message' => '成功建立 ' . $createdItems->count() . ' 筆物品',
            'data' => $createdItems->map->only(['id', 'uuid', 'name']),
        ], Response::HTTP_CREATED);
    }

    /**
     * 計算建立數量（含上限檢查）
     *
     * @param array $validated
     * @return int
     */
    private function calculateQuantity(array $validated): int
    {
        $maxQuantity = config('app.max_item_quantity');
        $quantity = max((int) ($validated['quantity'] ?? 1), 1);
        return min($quantity, $maxQuantity);
    }

    /**
     * 批次建立物品並關聯圖片
     *
     * @param array $data
     * @param int $quantity
     * @return Collection<Item>
     */
    private function createItemsWithImages(array $data, int $quantity): Collection
    {
        $createdItems = collect();

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $quantity; $i++) {
                $item = $this->itemService->create($data);

                // 處理圖片關聯（如果有提供）
                if (!empty($data['images'])) {
                    $this->itemImageService->attachImagesToItem($item, $data['images']);
                }

                $createdItems->push($item);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $createdItems;
    }

    /**
     * @param string $shortId
     * @return JsonResponse
     */
    public function show(string $shortId): JsonResponse
    {
        $item = $this->itemService->findByShortIdOrFail($shortId);

        return response()->json([
            'success' => true,
            'message' => '資料載入成功',
            'items' => [
                new ItemResource($item->load(['images', 'units', 'category', 'product.category']))
            ],
        ]);
    }

    /**
     * @param UpdateItemRequest $request
     * @param Item $item
     * @return JsonResponse
     */
    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $validated = $request->validated();

        // 1. 驗證圖片數量（僅計算 new + original）
        $images = $request->input('images', []);
        $activeImages = collect($images)->whereIn('status', ['new', 'original']);
        if ($activeImages->count() > 9) {
            return response()->json([
                'message' => '最多只能有 9 張圖片',
                'errors' => ['images' => ['最多只能有 9 張圖片']],
            ], 422);
        }

        // 2. 先處理 removed 的圖片
        $removedImages = collect($images)->where('status', 'removed');
        foreach ($removedImages as $imgObj) {
            $uuid = $imgObj['uuid'] ?? null;
            if (!$uuid) {
                continue;
            }
            $item->images()->detach($uuid);
            $image = \App\Models\ItemImage::where('uuid', $uuid)->first();
            if ($image) {
                $image->decrement('usage_count');
                if ($image->usage_count <= 0) {
                    $image->status = 'draft';
                }
                $image->save();
            }
        }

        // 3. 新增 new 的圖片
        $newImages = collect($images)->where('status', 'new');
        $loopIndex = 0;
        foreach ($newImages as $imgObj) {
            $uuid = $imgObj['uuid'] ?? null;
            if (!$uuid) {
                continue;
            }
            // 避免重複 attach
            if (!$item->images->contains('uuid', $uuid)) {
                $item->images()->attach($uuid, [
                    'sort_order' => ++$loopIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $image = \App\Models\ItemImage::where('uuid', $uuid)->first();
            if ($image) {
                $image->increment('usage_count');
                if ($image->usage_count === 1) {
                    $image->status = 'used';
                }
                $image->save();
            }
        }

        // 4. 原始圖片不異動
        // 5. 更新 item 其他欄位
        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'item' => $item->fresh(['images', 'units', 'category', 'product.category']),
        ]);
    }

    /**
     * @param Item $item
     * @return JsonResponse
     */
    public function destroy(Item $item): JsonResponse
    {
        $this->itemService->delete($item);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * 取得近期過期的商品列表
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);

        // 限制最多只能查詢 3 年（1095 天）
        if ($days < 1) {
            return response()->json([
                'success' => false,
                'message' => '查詢天數必須大於 0',
            ], 400);
        }

        if ($days > 1095) {
            return response()->json([
                'success' => false,
                'message' => '查詢天數最多為 1095 天（3 年）',
            ], 400);
        }

        $perPage = $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 100); // 限制在 1-100 之間

        $items = $this->itemService->getExpiringSoonItems($days, $perPage);
        $collection = new ItemCollection($items);
        $data = $collection->toArray($request);

        // 計算所有範圍的統計（一次查詢，高效能）
        $rangeStats = $this->itemService->getRangeStatistics([7, 30, 90, 180, 365, 1095]);
        $totalAll = $this->itemService->countItemsWithExpirationDate();

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => $data['meta'],
            'items' => $data['items'],
            'range_statistics' => $rangeStats,
            'total_all_with_expiration_date' => $totalAll,
        ]);
    }

    /**
     * 取得物品統計資料
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->input('period', 'all');
        $year = $request->input('year') ? (int) $request->input('year') : null;

        // 驗證時間範圍參數（all 已經包含在內）
        if (!in_array($period, ['all', 'year', 'month', 'week', 'three_months'])) {
            return response()->json([
                'success' => false,
                'message' => '無效的時間範圍參數',
            ], 400);
        }

        $statistics = $this->itemService->getStatistics($period, $year);

        // 將最貴前五名轉換為 Resource
        $statistics['top_expensive'] = ItemResource::collection($statistics['top_expensive']);

        // 將尚未使用的物品前五名轉換為 Resource
        if (isset($statistics['unused_items']['top_five'])) {
            $statistics['unused_items']['top_five'] = collect($statistics['unused_items']['top_five'])
                ->map(function ($data) {
                    return [
                        'item' => new ItemResource($data['item']),
                        'days_unused' => $data['days_unused'],
                    ];
                })->values()->all();
        }

        // 將已結案成本前五名轉換為 Resource
        if (isset($statistics['discarded_cost_stats']['top_five'])) {
            $statistics['discarded_cost_stats']['top_five'] = collect($statistics['discarded_cost_stats']['top_five'])
                ->map(function ($data) {
                    return [
                        'item' => new ItemResource($data['item']->load(['images', 'product'])),
                        'cost_per_day' => $data['cost_per_day'],
                        'usage_days' => $data['usage_days'],
                    ];
                })
                ->values()
                ->all();
        }

        // 將使用中成本前五名轉換為 Resource
        if (isset($statistics['in_use_cost_stats']['top_five'])) {
            $statistics['in_use_cost_stats']['top_five'] = collect($statistics['in_use_cost_stats']['top_five'])
                ->map(function ($data) {
                    return [
                        'item' => new ItemResource($data['item']->load(['images', 'product'])),
                        'cost_per_day' => $data['cost_per_day'],
                        'usage_days' => $data['usage_days'],
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'data' => $statistics,
        ]);
    }
}
