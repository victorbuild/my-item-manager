<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Models\LoginLog;

/**
 * 記錄使用者登入日誌
 * 當使用者登入時，將登入資訊記錄到資料庫
 */
class LogUserLogin
{
    /**
     * 處理事件
     *
     * @param UserLoggedIn $event
     * @return void
     */
    public function handle(UserLoggedIn $event): void
    {
        LoginLog::create([
            'user_id' => $event->user->id,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'logged_in_at' => now(),
        ]);
    }
}
