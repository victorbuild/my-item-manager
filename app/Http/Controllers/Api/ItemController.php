<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpiringSoonRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemCollection;
use App\Http\Resources\ItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\ItemImageService;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemImageService $itemImageService,
        private readonly ItemRepositoryInterface $itemRepository
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
            'product_short_id' => $request->input('product_short_id'),
            'statuses' => $request->filled('statuses') ? explode(',', $request->input('statuses')) : [],
            'sort' => $request->input('sort', 'default'),
        ];

        $perPage = $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 100); // 限制在 1-100 之間
        $items = $this->itemService->paginateWithFilters($filters, auth()->id(), $perPage);
        $collection = new ItemCollection($items);
        $data = $collection->toArray($request);

        return ApiResponse::success(
            data: $data['items'],
            message: '取得成功',
            meta: $data['meta']
        );
    }

    /**
     * 建立物品
     *
     * @param StoreItemRequest $request
     * @return JsonResponse
     * @throws \Exception 當批次建立物品失敗時拋出
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $quantity = $this->itemService->calculateQuantity($validated);

        $result = $this->itemService->createBatch($validated, $quantity, auth()->id());

        return response()->json([
            'success' => true,
            'message' => '成功建立 ' . $result['quantity'] . ' 筆物品',
            'data' => [
                'item' => $result['item'] ? new ItemResource($result['item']) : null,
                'quantity' => $result['quantity'],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * 顯示物品詳情
     *
     * @param  string  $shortId
     * @return JsonResponse
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 當物品不存在時拋出
     * @throws \Illuminate\Auth\Access\AuthorizationException 當非物品擁有者時拋出（403）
     */
    public function show(string $shortId): JsonResponse
    {
        $item = $this->itemRepository->findByShortIdOrFail($shortId);
        $this->authorize('view', $item);

        return response()->json([
            'success' => true,
            'message' => '資料載入成功',
            'data' => new ItemResource($item->load(['images', 'category', 'product.category'])),
        ]);
    }

    /**
     * 更新物品
     *
     * @param  UpdateItemRequest  $request
     * @param  Item  $item
     * @return JsonResponse
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 當物品不存在時拋出（route model binding）
     * @throws \Illuminate\Auth\Access\AuthorizationException 當非物品擁有者時拋出（403）
     * @throws \Illuminate\Validation\ValidationException 當表單驗證失敗時拋出（422）
     */
    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validated();
        $images = $request->input('images', []);

        // 驗證圖片數量（僅計算 new + original）
        if (!$this->itemImageService->validateImageCount($images, 9)) {
            return response()->json([
                'success' => false,
                'message' => '最多只能有 9 張圖片',
                'errors' => ['images' => ['最多只能有 9 張圖片']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 更新物品（包含圖片同步）
        $updatedItem = $this->itemService->update($item, $validated, $images);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => new ItemResource($updatedItem),
        ]);
    }

    /**
     * 刪除物品
     *
     * @param  Item  $item
     * @return JsonResponse
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 當物品不存在時拋出（route model binding）
     * @throws \Illuminate\Auth\Access\AuthorizationException 當非物品擁有者時拋出（403）
     */
    public function destroy(Item $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $this->itemService->delete($item);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * 取得近期過期的商品列表
     *
     * @param ExpiringSoonRequest $request
     * @return JsonResponse
     */
    public function expiringSoon(ExpiringSoonRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $days = $validated['days'];
        $perPage = $validated['per_page'];

        $items = $this->itemRepository->getExpiringSoonItems($days, $perPage, auth()->id());
        $collection = new ItemCollection($items);
        $data = $collection->toArray($request);

        // 取得統計資料（整合方法）
        $statistics = $this->itemService->getExpiringSoonStatistics($days, auth()->id());

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => $data['meta'],
            'data' => $data['items'],
            'range_statistics' => $statistics['range_statistics'],
            'total_all_with_expiration_date' => $statistics['total_all_with_expiration_date'],
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
