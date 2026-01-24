<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// 必要命名路由：未登入導向 login 時使用（避免 Route [login] not defined）
Route::get('/login', function () {
    return view('welcome');
})->name('login');

// SPA 認證路由
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// VUE SPA
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
