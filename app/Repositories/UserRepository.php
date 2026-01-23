<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * 使用者資料存取層
 *
 * 負責處理 User 模型的資料庫操作
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * 建立使用者
     *
     * @param  array{name: string, email: string, password: string}  $data  使用者資料（password 由 Model hashed cast 處理）
     */
    public function create(array $data): User
    {
        return User::create($data);
    }
}
