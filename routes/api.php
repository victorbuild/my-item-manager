<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemImageController;
use App\Http\Controllers\Api\ItemUnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::apiResource('/products', ProductController::class);
    Route::get('/items/expiring-soon', [ItemController::class, 'expiringSoon']);
    Route::get('/items/statistics/overview', [ItemController::class, 'statistics']);
    Route::apiResource('items', ItemController::class);
    Route::post('/item-images', [ItemImageController::class, 'store']);
    Route::apiResource('item-units', ItemUnitController::class);
});
