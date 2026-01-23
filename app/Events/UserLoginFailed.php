<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 使用者登入失敗事件
 * 當使用者登入失敗時觸發此事件
 */
class UserLoginFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * 建立新的事件實例
     *
     * @param string $email 嘗試登入的 Email
     * @param string|null $ipAddress 登入 IP 位址
     * @param string|null $userAgent 使用者代理（瀏覽器資訊）
     */
    public function __construct(
        public string $email,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {
    }
}
