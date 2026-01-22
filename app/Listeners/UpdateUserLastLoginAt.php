<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;

/**
 * 更新使用者最後登入時間
 * 當使用者登入時，更新 User 模型的 last_login_at 欄位
 */
class UpdateUserLastLoginAt
{
    /**
     * 處理事件
     *
     * @param UserLoggedIn $event
     * @return void
     */
    public function handle(UserLoggedIn $event): void
    {
        $event->user->update(['last_login_at' => now()]);
    }
}
