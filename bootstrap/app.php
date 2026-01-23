<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 統一處理 API 路由和認證路由的所有異常，確保都返回 JSON 格式
        $exceptions->render(function (Exception $e, Request $request) {
            // 處理 API 路由和認證路由（login, register, logout）
            $isApiRoute = $request->is('api/*');
            $isAuthRoute = in_array($request->path(), ['login', 'register', 'logout']);
            
            if (!$isApiRoute && !$isAuthRoute) {
                return null;
            }
            
            // API 路由一律返回 JSON，認證路由需要是 JSON 請求
            if (!$isApiRoute && !$request->expectsJson() && !$request->isJson()) {
                return null;
            }

            // 決定狀態碼和訊息
            [$statusCode, $message, $errors] = match (true) {
                $e instanceof \Illuminate\Validation\ValidationException => [
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    '驗證失敗',
                    $e->errors(),
                ],
                $e instanceof \Illuminate\Auth\AuthenticationException => [
                    Response::HTTP_UNAUTHORIZED,
                    // 如果是預設訊息或空訊息，使用中文訊息；否則使用自定義訊息
                    in_array($e->getMessage(), ['Unauthenticated.', '']) ? '未授權，請先登入' : $e->getMessage(),
                    null,
                ],
                $e instanceof \Illuminate\Auth\Access\AuthorizationException => [
                    Response::HTTP_FORBIDDEN,
                    '沒有權限執行此操作',
                    null,
                ],
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
                $e instanceof NotFoundHttpException => [
                    Response::HTTP_NOT_FOUND,
                    '找不到指定的資源',
                    null,
                ],
                $e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException => [
                    Response::HTTP_TOO_MANY_REQUESTS,
                    $e->getMessage() ?: '嘗試次數過多，請稍後再試',
                    null,
                ],
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException => [
                    $e->getStatusCode(),
                    // 優先使用異常的訊息，如果沒有則使用預設訊息
                    $e->getMessage() ?: match ($e->getStatusCode()) {
                        Response::HTTP_NOT_FOUND => '找不到指定的資源',
                        Response::HTTP_FORBIDDEN => '沒有權限執行此操作',
                        Response::HTTP_METHOD_NOT_ALLOWED => '不允許的請求方法',
                        Response::HTTP_TOO_MANY_REQUESTS => '嘗試次數過多，請稍後再試',
                        default => '發生錯誤，請稍後再試',
                    },
                    null,
                ],
                default => [
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    '發生錯誤，請稍後再試',
                    null,
                ],
            };

            // 記錄 500 錯誤
            if ($statusCode >= 500) {
                Log::error('API 請求發生錯誤', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
            }

            // 根據 Accept header 決定回應格式（目前只支援 JSON，預留 XML 空間）
            $acceptHeader = $request->header('Accept', 'application/json');
            $responseData = ['success' => false, 'message' => $message];
            if ($errors) {
                $responseData['errors'] = $errors;
            }

            // 未來可以在這裡加入 XML 支援
            // if (str_contains($acceptHeader, 'xml')) {
            //     return response()->xml($responseData, $statusCode);
            // }

            return response()->json($responseData, $statusCode);
        });
    })->create();
