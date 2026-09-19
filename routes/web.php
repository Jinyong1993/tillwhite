<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * 인증 관련 라우트
 *
 * Vue에서 Laravel 서버로 요청하는 인증 관련 기능을 정의한다.
 * SPA 화면을 반환하는 catch-all 라우트보다 먼저 작성하여
 * 서버에서 처리해야 하는 요청과 Vue 화면 요청을 명확하게 구분한다.
 */

// 로그인
Route::post('/tillwhite/login', [AuthController::class, 'login']);
// 현재 로그인 사용자 조회
Route::get('/tillwhite/auth/me', [AuthController::class, 'me']);
// 로그아웃
Route::post('/tillwhite/logout', [AuthController::class, 'logout']);

/**
 * Till White SPA 페이지
 *
 * /tillwhite 아래의 GET 요청은 Laravel에서 개별 화면을 만들지 않고
 * tillwhite.blade.php를 반환한 뒤 Vue Router가 실제 화면을 결정한다.
 *
 * 따라서 새로운 Vue 페이지가 추가되더라도
 * Laravel에 GET 라우트를 하나씩 추가할 필요가 없다.
 *
 * 서버에서 직접 처리해야 하는 API 및 인증 라우트는
 * 이 catch-all 라우트보다 위에 정의한다.
 */
Route::get('/tillwhite/{path?}', fn () => view('tillwhite'))
    ->where('path', '.*');