<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemUnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::prefix('api')->group(function () {
    Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'me']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);

        Route::apiResource('items', ItemController::class);
    });

    Route::apiResource('item-units', ItemUnitController::class);
    Route::post('/upload-temp-image', [ImageUploadController::class, 'uploadTemp']);
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
