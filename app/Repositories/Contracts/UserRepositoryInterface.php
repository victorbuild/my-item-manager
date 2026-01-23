<?php

namespace App\Repositories\Contracts;

use App\Models\User;

/**
 * 使用者資料存取介面
 *
 * 定義 User 資料存取的方法契約
 */
interface UserRepositoryInterface
{
    /**
     * 建立使用者
     *
     * @param  array{name: string, email: string, password: string}  $data  使用者資料（password 由 Model hashed cast 處理）
     */
    public function create(array $data): User;
}
