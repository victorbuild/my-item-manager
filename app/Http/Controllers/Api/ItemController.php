<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Resources\ItemCollection;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $itemService) {}

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
        ];

        $items = $this->itemService->paginateWithFilters($filters);
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
     * @param StoreItemRequest $request
     * @return JsonResponse
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $quantity = max((int) ($validated['quantity'] ?? 1), 1);

        $createdItems = [];

        for ($i = 0; $i < $quantity; $i++) {
            $item = $this->itemService->create($validated);

            if (!empty($validated['images'])) {
                $loopIndex = 0;
                foreach ($validated['images'] as $imgObj) {
                    if (($imgObj['status'] ?? null) !== 'new' || empty($imgObj['uuid'])) {
                        continue;
                    }

                    // 使用已存在的圖片 UUID 建立多對多關聯
                    $item->images()->attach($imgObj['uuid'], [
                        'sort_order' => $loopIndex = ($loopIndex ?? 0) + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // 更新圖片使用次數
                    $image = \App\Models\ItemImage::where('uuid', $imgObj['uuid'])->first();
                    if ($image) {
                        $image->increment('usage_count');
                        if ($image->status === 'draft') {
                            $image->status = 'used';
                            $image->save();
                        }
                    }
                }
            }

            $createdItems[] = $item;
        }

        return response()->json([
            'success' => true,
            'message' => '成功建立 ' . count($createdItems) . ' 筆物品',
            'items' => collect($createdItems)->map->only(['id', 'uuid', 'name']),
        ], 201);
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
     * @param Request $request
     * @param Item $item
     * @return JsonResponse
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'barcode' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',

            // 時間欄位
            'purchased_at' => 'nullable|date',
            'received_at' => 'nullable|date',
            'used_at' => 'nullable|date',
            'discarded_at' => 'nullable|date',

            'discard_note' => 'nullable',

            // 狀態與備註
            'is_discarded' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'item' => $item,
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
}
