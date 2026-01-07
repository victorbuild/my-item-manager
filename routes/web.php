<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemImageController;
use App\Http\Controllers\Api\ItemUnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);

    Route::apiResource('/products', ProductController::class);
    Route::get('/items/expiring-soon', [ItemController::class, 'expiringSoon']);
    Route::apiResource('items', ItemController::class);
    Route::post('/item-images', [ItemImageController::class, 'store']);
    Route::apiResource('item-units', ItemUnitController::class);
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
