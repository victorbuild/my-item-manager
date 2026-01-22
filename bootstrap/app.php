<?php

use Exception;
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
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found.'
                ], Response::HTTP_NOT_FOUND);
            }
        });

        // 統一處理 API 路由的 Exception
        $exceptions->render(function (Exception $e, Request $request) {
            if ($request->is('api/*')) {
                Log::error('API 請求發生錯誤', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '發生錯誤，請稍後再試',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        });
    })->create();
