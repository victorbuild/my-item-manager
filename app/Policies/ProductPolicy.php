<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * 是否可檢視（僅擁有者）
     *
     * @param  User  $user
     * @param  Product  $product
     * @return bool
     */
    public function view(User $user, Product $product): bool
    {
        return (int) $product->user_id === $user->id;
    }

    /**
     * 是否可更新（僅擁有者）
     *
     * @param  User  $user
     * @param  Product  $product
     * @return bool
     */
    public function update(User $user, Product $product): bool
    {
        return (int) $product->user_id === $user->id;
    }

    /**
     * 是否可刪除（僅擁有者）
     *
     * @param  User  $user
     * @param  Product  $product
     * @return bool
     */
    public function delete(User $user, Product $product): bool
    {
        return (int) $product->user_id === $user->id;
    }
}
