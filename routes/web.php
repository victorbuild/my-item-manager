<?php

use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemUnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::apiResource('items', ItemController::class);
    Route::apiResource('item-units', ItemUnitController::class);
    Route::post('/upload-temp-image', [ImageUploadController::class, 'uploadTemp']);
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
