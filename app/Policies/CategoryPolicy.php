<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * 是否可檢視（僅擁有者）
     *
     * @param User $user 使用者實例
     * @param Category $category 分類實例
     */
    public function view(User $user, Category $category): bool
    {
        return (int) $category->user_id === $user->id;
    }

    /**
     * 是否可更新（僅擁有者）
     *
     * @param User $user 使用者實例
     * @param Category $category 分類實例
     */
    public function update(User $user, Category $category): bool
    {
        return (int) $category->user_id === $user->id;
    }

    /**
     * 是否可刪除（僅擁有者）
     *
     * @param User $user 使用者實例
     * @param Category $category 分類實例
     */
    public function delete(User $user, Category $category): bool
    {
        return (int) $category->user_id === $user->id;
    }
}
