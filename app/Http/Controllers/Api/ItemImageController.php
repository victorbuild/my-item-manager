<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemImageRequest;
use App\Http\Resources\ItemImageResource;
use App\Http\Responses\ApiResponse;
use App\Services\ItemImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * 物品圖片控制器
 */
class ItemImageController extends Controller
{
    public function __construct(
        private readonly ItemImageService $itemImageService
    ) {
    }

    /**
     * 上傳圖片
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 當圖片上傳、處理或資料庫操作失敗時（由 Service 層拋出）
     */
    public function store(StoreItemImageRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $userId = $request->user()->id;

        $itemImage = $this->itemImageService->uploadImage($file, $userId);

        return ApiResponse::success(
            data: new ItemImageResource($itemImage),
            message: '圖片上傳成功',
            status: Response::HTTP_OK
        );
    }
}
