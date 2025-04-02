<?php

use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\ItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::apiResource('items', ItemController::class);
    Route::post('/upload-temp-image', [ImageUploadController::class, 'uploadTemp']);
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
