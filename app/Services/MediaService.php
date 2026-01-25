<?php

namespace App\Services;

use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class MediaService
{
    public function __construct(
        private readonly ItemImageRepositoryInterface $itemImageRepository
    ) {
    }

    /**
     * 取得用戶的圖片列表（帶篩選）
     *
     * @param int $userId 用戶 ID
     * @param string|null $status 狀態篩選
     * @param bool|null $hasItems 是否有關聯：true=有關聯, false=沒有關聯, null=全部
     * @param int $perPage 每頁筆數
     */
    public function paginateForUser(
        int $userId,
        ?string $status = null,
        ?bool $hasItems = null,
        int $perPage = 24
    ): LengthAwarePaginator {
        $query = ItemImage::query()->where('user_id', $userId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        // 篩選是否有關聯到 items
        if ($hasItems !== null) {
            if ($hasItems === true) {
                // 只顯示有關聯的
                $query->whereHas('items');
            } else {
                // 只顯示沒有關聯的
                $query->whereDoesntHave('items');
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 取得用戶未使用的圖片列表
     *
     * @param int $userId 用戶 ID
     * @param int $perPage 每頁筆數
     */
    public function paginateUnusedForUser(int $userId, int $perPage = 50): LengthAwarePaginator
    {
        return ItemImage::query()
            ->where('user_id', $userId)
            ->whereDoesntHave('items')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 取得用戶的圖片配額資訊
     *
     * @param int $userId 用戶 ID
     * @return array{used: int, limit: int|null, is_unlimited: bool, message: string, percentage: float}
     */
    public function getQuotaInfo(int $userId): array
    {
        // 計算當前用戶的所有圖片數量（直接通過 user_id）
        $userImageCount = ItemImage::query()->where('user_id', $userId)->count();

        // 暫時：2026年新年限時開放，不限制數量
        // 未來可以根據用戶等級設定配額：
        // - 一般會員：10張
        // - 訂閱會員：1000張
        // - 最高等級：10000張（設定上限以防萬一）
        $isUnlimited = true; // 暫時無限制
        $limit = null; // 無限制
        $message = '2026年新年限時開放，不限制多少數量圖片';

        // 計算百分比（無限制時為 0）
        $percentage = 0.0;
        // 未來實作會員等級時，$isUnlimited 會動態設定，此時才會計算百分比
        // if (!$isUnlimited && $limit !== null && $limit > 0) {
        //     $percentage = min(100, round(($userImageCount / $limit) * 100, 1));
        // }

        return [
            'used' => $userImageCount,
            'limit' => $limit, // null 表示無限制
            'is_unlimited' => $isUnlimited,
            'message' => $message,
            'percentage' => $percentage,
        ];
    }

    /**
     * 根據 UUID 取得圖片（包含關聯的 items）
     * 注意：此方法不過濾 user_id，權限檢查應由 Controller 層的 Policy 處理
     *
     * @param string $uuid 圖片 UUID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 當圖片不存在時
     */
    public function findByUuidForUser(string $uuid): ItemImage
    {
        $image = $this->itemImageRepository->findByUuid($uuid);

        if (! $image) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        return $image->load('items');
    }
}
