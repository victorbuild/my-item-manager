<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    /**
     * 是否可檢視（僅擁有者）
     *
     * @param  User  $user
     * @param  Item  $item
     * @return bool
     */
    public function view(User $user, Item $item): bool
    {
        return (int) $item->user_id === $user->id;
    }

    /**
     * 是否可更新（僅擁有者）
     *
     * @param  User  $user
     * @param  Item  $item
     * @return bool
     */
    public function update(User $user, Item $item): bool
    {
        return (int) $item->user_id === $user->id;
    }

    /**
     * 是否可刪除（僅擁有者）
     *
     * @param  User  $user
     * @param  Item  $item
     * @return bool
     */
    public function delete(User $user, Item $item): bool
    {
        return (int) $item->user_id === $user->id;
    }
}
