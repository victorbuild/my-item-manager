<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $itemService)
    {
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
        ];

        $items = $this->itemService->paginateWithFilters($filters);

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'items' => $items->items(),
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

            $gcsDisk = Storage::disk('gcs');
            $manager = new ImageManager(new Driver());

            // 處理圖片（只處理 status=new 的圖片）
            if (!empty($validated['images'])) {
                foreach ($validated['images'] as $imgObj) {
                    if (($imgObj['status'] ?? null) !== 'new') {
                        continue;
                    }
                    $gcsPath = $imgObj['path'];
                    if (!$gcsDisk->exists($gcsPath)) {
                        \Log::warning("Temporary image not found on GCS: {$gcsPath}");
                        continue;
                    }

                    $imageData = $gcsDisk->get($gcsPath);
                    $img = $manager->read($imageData);

                    $extension = pathinfo($gcsPath, PATHINFO_EXTENSION) ?: 'png';
                    $uuid = $item->uuid;
                    $basename = Str::random(40);
                    $originalName = "{$basename}.{$extension}";
                    $webpName = "{$basename}.webp";

                    $gcsDisk->put("item-images/{$uuid}/original/{$originalName}", $imageData);

                    $preview = $img->scaleDown(width: 600, height: 800)->toWebp(85);
                    $thumb = $img->scaleDown(width: 300, height: 400)->toWebp(75);

                    $gcsDisk->put("item-images/{$uuid}/preview/{$webpName}", $preview);
                    $gcsDisk->put("item-images/{$uuid}/thumb/{$webpName}", $thumb);

                    $item->images()->create([
                        'image_path' => $basename,
                        'original_extension' => strtolower($extension),
                    ]);

                    $gcsDisk->delete($gcsPath);
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
