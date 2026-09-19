<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// 기본 페이지
Route::get('/tillwhite/{path?}', fn () => view('tillwhite'))->where('path', '.*');

// 로그인
Route::post('/tillwhite/login', [AuthController::class, 'login']);