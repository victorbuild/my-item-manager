<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 使用者登入事件
 * 當使用者成功登入時觸發此事件
 */
class UserLoggedIn
{
    use Dispatchable, SerializesModels;

    /**
     * 建立新的事件實例
     *
     * @param User $user 登入的使用者
     * @param string|null $ipAddress 登入 IP 位址
     * @param string|null $userAgent 使用者代理（瀏覽器資訊）
     */
    public function __construct(
        public User $user,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {
    }
}
