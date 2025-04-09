<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ItemController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Item::with(['images', 'units']);

        // 搜尋關鍵字處理
        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        // 分頁 + 排序
        $items = $query->orderBy('id', 'desc')->paginate(10);

        // 加工圖片網址
        $itemsTransformed = $items->getCollection()->map(function ($item) {
            $item->images = $item->images->map(function ($image) use ($item) {
                $uuid = $item->uuid;
                $filename = $image->image_path;

                $image->thumb_url = Storage::disk('local')->url("item-images/{$uuid}/thumb/{$filename}.webp");

                return $image;
            });

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'items' => $itemsTransformed,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric',
            'purchased_at' => 'nullable|date',
            'category_id' => 'nullable|exists:categories,id',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'units' => 'nullable|array',
            'units.*' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ]);

        $item = Item::create($validated);

        $disk = Storage::disk('public');
        $manager = new ImageManager(
            new Driver()
        );

        // 移動圖片到正式資料夾
        if (!empty($request->image_urls)) {
            foreach ($request->image_urls as $tempUrl) {
                $tempPath = str_replace('/storage/', '', parse_url($tempUrl, PHP_URL_PATH)); // 轉相對路徑

                if (!$disk->exists($tempPath)) continue;

                $imageData = $disk->get($tempPath);
                $img = $manager->read($imageData);

                $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'png';
                $uuid = $item->uuid;
                $basename = Str::random(40);
                $originalName = "{$basename}.{$extension}";
                $webpName = "{$basename}.webp";

                // 原圖：保留原格式
                $disk->move($tempPath, "item-images/{$uuid}/original/{$originalName}");
                $width = $img->width();
                $height = $img->height();

                // 預覽圖與縮圖（webp）
                $preview = $img->scaleDown(width: 600, height: 800)->toWebp(85);
                $thumb = $img->scaleDown(width: 300, height: 400)->toWebp(75);

                $disk->put("item-images/{$uuid}/preview/{$webpName}", $preview);
                $disk->put("item-images/{$uuid}/thumb/{$webpName}", $thumb);

                $item->images()->create([
                    'image_path' => $basename,
                    'original_extension' => strtolower($extension),
                ]);
            }
        }

        // 單品處理
        $units = $request->input('units');
        if ($units && is_array($units) && count(array_filter($units))) {
            foreach ($units as $index => $unitName) {
                $item->units()->create([
                    'unit_number' => $index + 1,
                    'notes' => $unitName,
                ]);
            }
        } else {
            // 如果沒提供單品，自動新增一個
            $item->units()->create([
                'unit_number' => 1,
                'notes' => null,
            ]);
        }

        return response()->json($item, 201);
    }

    /**
     * @param string $shortId
     * @return JsonResponse
     */
    public function show(string $shortId): JsonResponse
    {
        $item = Item::where('short_id', $shortId)->with(['images', 'units'])->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => '資料載入成功',
            'items' => [
                new ItemResource($item->load(['images', 'units', 'category']))
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
            'quantity' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric',
            'purchased_at' => 'nullable|date',
            'is_discarded' => 'boolean',
            'discarded_at' => 'nullable|date',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    /**
     * @param Item $item
     * @return JsonResponse
     */
    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json(['message' => 'Item deleted.']);
    }
}
