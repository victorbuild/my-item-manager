<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ImageUrlHelper;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService
    ) {
    }

    /**
     * 取得所有草稿圖片（用於媒體庫）
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 24);
        $status = $request->get('status', 'draft'); // 預設只顯示草稿
        $hasItemsParam = $request->get('has_items'); // 是否有關聯：true=有關聯, false=沒有關聯, null=全部

        $userId = auth()->id() ?? 0;

        // 轉換 has_items 參數為 boolean
        $hasItems = null;
        if ($hasItemsParam === 'true' || $hasItemsParam === true) {
            $hasItems = true;
        } elseif ($hasItemsParam === 'false' || $hasItemsParam === false) {
            $hasItems = false;
        }

        // 使用 Service 層處理查詢邏輯
        $images = $this->mediaService->paginateForUser($userId, $status, $hasItems, $perPage);

        // 為每個圖片產生縮圖 URL
        $transformedData = $images->getCollection()->map(function ($image) {
            $urls = ImageUrlHelper::generateSignedUrls($image, config('images.url_expiration_minutes.default', 60));

            return [
                'uuid' => $image->uuid,
                'image_path' => $image->image_path,
                'original_extension' => $image->original_extension,
                'status' => $image->status,
                'usage_count' => $image->usage_count,
                'created_at' => $image->created_at,
                'updated_at' => $image->updated_at,
                'thumb_url' => $urls['thumb_url'],
                'preview_url' => $urls['preview_url'],
            ];
        });

        // 計算用戶的圖片配額資訊
        $quotaInfo = $this->mediaService->getQuotaInfo($userId);

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
     * 取得未使用的圖片列表（用於選擇）
     */
    public function unused(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? 0;
        $perPage = $request->get('per_page', 50);

        // 使用 Service 層處理查詢邏輯
        $images = $this->mediaService->paginateUnusedForUser($userId, $perPage);

        // 為每個圖片產生縮圖 URL
        $transformedData = $images->getCollection()->map(function ($image) {
            $urls = ImageUrlHelper::generateSignedUrls($image, config('images.url_expiration_minutes.default', 60));

            return [
                'uuid' => $image->uuid,
                'image_path' => $image->image_path,
                'original_extension' => $image->original_extension,
                'status' => $image->status,
                'created_at' => $image->created_at,
                'thumb_url' => $urls['thumb_url'],
                'preview_url' => $urls['preview_url'],
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
        // 使用 Service 層處理查詢邏輯（不過濾 user_id）
        $image = $this->mediaService->findByUuidForUser($uuid);

        // 使用 Policy 檢查權限（如果沒有權限會拋出 AuthorizationException，返回 403）
        $this->authorize('view', $image);

        $urls = ImageUrlHelper::generateSignedUrls($image, config('images.url_expiration_minutes.default', 60));

        /** @var \Illuminate\Database\Eloquent\Collection<int, Item> $itemsCollection */
        $itemsCollection = $image->items;
        $items = $itemsCollection->map(function (Item $item) {
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
            'thumb_url' => $urls['thumb_url'],
            'preview_url' => $urls['preview_url'],
            'original_url' => $urls['original_url'],
            'items' => $items,
        ]);
    }

    /**
     * 刪除圖片
     */
    public function destroy(string $uuid): JsonResponse
    {
        $image = $this->mediaService->findByUuidForUser($uuid);

        $this->authorize('delete', $image);

        try {
            $this->mediaService->delete($image);

            return response()->json([
                'success' => true,
                'message' => '圖片刪除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
