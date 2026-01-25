<?php

namespace App\Policies;

use App\Models\ItemImage;
use App\Models\User;

/**
 * 媒體資源 Policy
 *
 * 目前支援 ItemImage，未來可擴充支援 Media 等其他媒體類型
 */
class MediaPolicy
{
    /**
     * 是否可檢視（僅擁有者）
     *
     * @param User $user 使用者實例
     * @param ItemImage $itemImage 圖片實例
     */
    public function view(User $user, ItemImage $itemImage): bool
    {
        return (int) $itemImage->user_id === $user->id;
    }

    /**
     * 是否可更新（僅擁有者）
     *
     * @param User $user 使用者實例
     * @param ItemImage $itemImage 圖片實例
     */
    public function update(User $user, ItemImage $itemImage): bool
    {
        return (int) $itemImage->user_id === $user->id;
    }

    /**
     * 是否可刪除（僅擁有者）
     *
     * @param User $user 使用者實例
     * @param ItemImage $itemImage 圖片實例
     */
    public function delete(User $user, ItemImage $itemImage): bool
    {
        return (int) $itemImage->user_id === $user->id;
    }
}
