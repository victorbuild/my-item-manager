<?php

namespace App\Exceptions;

use RuntimeException;

class UnprocessableEntityException extends RuntimeException
{
    /**
     * 建立 422（Unprocessable Entity）例外
     *
     * @param string $message 錯誤訊息
     * @param int $code 錯誤代碼
     * @param \Throwable|null $previous 上一個例外
     */
    public function __construct(string $message = '無法處理此請求', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
