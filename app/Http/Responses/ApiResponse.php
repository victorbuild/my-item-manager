<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * API 回應工具
 *
 * 用於統一 JSON 回應格式，避免 Controller 各自回傳不同 key（如 items/data）。
 */
final class ApiResponse
{
    /**
     * 成功回應（統一使用 data）
     *
     * @param mixed $data 回應資料
     * @param string $message 訊息
     * @param int $status HTTP 狀態碼
     * @param array<string, mixed>|null $meta 分頁等額外資訊（可選）
     * @return JsonResponse
     */
    public static function success(
        mixed $data,
        string $message = '取得成功',
        int $status = Response::HTTP_OK,
        ?array $meta = null
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * 成功建立回應（201）
     *
     * @param mixed $data 回應資料
     * @param string $message 訊息
     * @param array<string, mixed>|null $meta 額外資訊（可選）
     * @return JsonResponse
     */
    public static function created(mixed $data, string $message = '建立成功', ?array $meta = null): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED, $meta);
    }
}
