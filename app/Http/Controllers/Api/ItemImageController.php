<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemImageRequest;
use App\Http\Resources\ItemImageResource;
use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Services\ItemImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 物品圖片控制器
 */
class ItemImageController extends Controller
{
    public function __construct(
        private readonly ItemImageService $itemImageService,
        private readonly ItemImageRepositoryInterface $itemImageRepository
    ) {
    }

    /**
     * 上傳圖片
     */
    public function store(StoreItemImageRequest $request): JsonResponse
    {
        try {
            $file = $request->file('image');
            $userId = $request->user()->id;

            $result = $this->itemImageService->uploadImage($file, $userId);

            // 取得圖片記錄
            $itemImage = $this->itemImageRepository->findByUuid($result['uuid']);
            if (! $itemImage) {
                throw new \Exception('無法找到上傳的圖片記錄');
            }

            return ApiResponse::success(
                data: new ItemImageResource($itemImage),
                message: '圖片上傳成功',
                status: Response::HTTP_OK
            );
        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '圖片上傳失敗',
                'error' => config('app.debug') ? $e->getMessage() : '伺服器錯誤',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
