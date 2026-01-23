<?php

namespace App\Listeners;

use App\Events\UserLoginFailed;
use App\Models\LoginLog;

/**
 * 記錄使用者登入失敗日誌
 * 當使用者登入失敗時，將失敗資訊記錄到資料庫
 */
class LogUserLoginFailed
{
    /**
     * 處理事件
     *
     * @param UserLoginFailed $event
     * @return void
     */
    public function handle(UserLoginFailed $event): void
    {
        LoginLog::create([
            'user_id' => null, // 失敗時沒有 user_id
            'email' => $event->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'status' => 'failed',
            'logged_in_at' => now(),
        ]);
    }
}
