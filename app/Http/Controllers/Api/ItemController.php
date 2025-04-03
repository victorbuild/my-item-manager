<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function index()
    {
        $items = Item::with(['images', 'units'])
            ->orderBy('id', 'desc')
            ->paginate(10);

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
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'units' => 'nullable|array',
            'units.*' => 'nullable|string|max:255',
        ]);

        $item = Item::create($validated);

        // 移動圖片到正式資料夾
        if (!empty($request->image_urls)) {
            foreach ($request->image_urls as $tempUrl) {
                $tempPath = str_replace('/storage/', '', parse_url($tempUrl, PHP_URL_PATH)); // 轉相對路徑
                $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'png'; // 預設 png
                $newPath = 'item-images/' . $item->id . '/' . Str::random(40) . '.' . $extension;

                if (Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->move($tempPath, $newPath);
                    $item->images()->create([
                        'image_path' => $newPath
                    ]);
                }
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
                new ItemResource($item->load(['images', 'units']))
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
