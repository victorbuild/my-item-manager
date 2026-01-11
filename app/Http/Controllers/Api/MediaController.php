<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * 取得所有草稿圖片（用於媒體庫）
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 24);
        $status = $request->get('status', 'draft'); // 預設只顯示草稿
        $hasItems = $request->get('has_items'); // 是否有關聯：true=有關聯, false=沒有關聯, null=全部

        $userId = auth()->id();

        // 只顯示當前用戶的圖片
        $query = ItemImage::where('user_id', $userId);

        if ($status) {
            // @phpstan-ignore-next-line
            $query->where('status', $status);
        }

        // 篩選是否有關聯到 items
        if ($hasItems !== null) {
            if ($hasItems === 'true' || $hasItems === true) {
                // 只顯示有關聯的（usage_count > 0 或有關聯的 items）
                $query->whereHas('items');
            } elseif ($hasItems === 'false' || $hasItems === false) {
                // 只顯示沒有關聯的（usage_count = 0 且沒有關聯的 items）
                $query->whereDoesntHave('items');
            }
        }

        $images = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 為每個圖片產生縮圖 URL
        $transformedData = $images->getCollection()->map(function ($image) {
            $thumbPath = "item-images/{$image->uuid}/thumb_{$image->image_path}.webp";
            $previewPath = "item-images/{$image->uuid}/preview_{$image->image_path}.webp";

            return [
                'uuid' => $image->uuid,
                'image_path' => $image->image_path,
                'original_extension' => $image->original_extension,
                'status' => $image->status,
                'usage_count' => $image->usage_count,
                'created_at' => $image->created_at,
                'updated_at' => $image->updated_at,
                'thumb_url' => Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60)),
                'preview_url' => Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(60)),
            ];
        });

        // 計算用戶的圖片配額資訊
        $quotaInfo = $this->getQuotaInfo();

        return response()->json([
            'data' => $transformedData,
            'current_page' => $images->currentPage(),
            'last_page' => $images->lastPage(),
            'per_page' => $images->perPage(),
            'total' => $images->total(),
            'quota' => $quotaInfo,
        ]);
    }

    /**
     * 取得用戶圖片配額資訊
     */
    private function getQuotaInfo(): array
    {
        $userId = auth()->id();

        // 計算當前用戶的所有圖片數量（直接通過 user_id）
        $userImageCount = ItemImage::where('user_id', $userId)->count();

        // 暫時：2026年新年限時開放，不限制數量
        // 未來可以根據用戶等級設定配額：
        // - 一般會員：10張
        // - 訂閱會員：1000張
        // - 最高等級：10000張（設定上限以防萬一）
        $isUnlimited = true; // 暫時無限制
        $limit = null; // 無限制
        $message = '2026年新年限時開放，不限制多少數量圖片';

        return [
            'used' => $userImageCount,
            'limit' => $limit, // null 表示無限制
            'is_unlimited' => $isUnlimited,
            'message' => $message,
            'percentage' => $isUnlimited ? 0 : min(100, round(($userImageCount / $limit) * 100, 1)),
        ];
    }

    /**
     * 取得未使用的圖片列表（用於選擇）
     */
    public function unused(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $perPage = $request->get('per_page', 50);

        // 只顯示當前用戶的、沒有關聯的圖片
        $images = ItemImage::where('user_id', $userId)
            ->whereDoesntHave('items')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 為每個圖片產生縮圖 URL
        $transformedData = $images->getCollection()->map(function ($image) {
            $thumbPath = "item-images/{$image->uuid}/thumb_{$image->image_path}.webp";
            $previewPath = "item-images/{$image->uuid}/preview_{$image->image_path}.webp";

            return [
                'uuid' => $image->uuid,
                'image_path' => $image->image_path,
                'original_extension' => $image->original_extension,
                'status' => $image->status,
                'created_at' => $image->created_at,
                'thumb_url' => Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60)),
                'preview_url' => Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(60)),
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $images->currentPage(),
            'last_page' => $images->lastPage(),
            'per_page' => $images->perPage(),
            'total' => $images->total(),
        ]);
    }

    /**
     * 取得圖片詳細資訊（包含關聯的 items）
     */
    public function show(string $uuid): JsonResponse
    {
        $userId = auth()->id();

        // 只允許查看當前用戶的圖片
        // @phpstan-ignore-next-line
        $image = ItemImage::where('user_id', $userId)
            ->with('items')
            ->findOrFail($uuid);

        $thumbPath = "item-images/{$image->uuid}/thumb_{$image->image_path}.webp";
        $previewPath = "item-images/{$image->uuid}/preview_{$image->image_path}.webp";
        $originalPath = "item-images/{$image->uuid}/original_{$image->image_path}.{$image->original_extension}";

        // @phpstan-ignore-next-line
        $items = $image->items()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'short_id' => $item->short_id,
                'name' => $item->name,
            ];
        });

        return response()->json([
            'uuid' => $image->uuid,
            'image_path' => $image->image_path,
            'original_extension' => $image->original_extension,
            'status' => $image->status,
            'usage_count' => $image->usage_count,
            'created_at' => $image->created_at,
            'updated_at' => $image->updated_at,
            'thumb_url' => Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60)),
            'preview_url' => Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(60)),
            'original_url' => Storage::disk('gcs')->temporaryUrl($originalPath, now()->addMinutes(60)),
            'items' => $items,
        ]);
    }
}
