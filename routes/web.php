<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// SPA 認證路由
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// VUE SPA 
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
