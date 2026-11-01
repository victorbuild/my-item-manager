<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Route;

// 必要命名路由：未登入導向 login 時使用（避免 Route [login] not defined）
Route::get('/login', function () {
    return view('welcome');
})->name('login');

// SPA 認證路由
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login-ip');
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/verify-email', fn () => view('welcome'))->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

// VUE SPA
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
