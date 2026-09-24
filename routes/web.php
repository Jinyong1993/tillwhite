<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

/**
 * Till White 인증 API
 *
 * 로그인, 현재 로그인 사용자 조회, 로그아웃을 처리합니다.
 */

// 로그인
Route::post('/tillwhite/login', [AuthController::class, 'login']);

// 현재 로그인 사용자 및 권한 정보 조회
Route::get('/tillwhite/auth/me', [AuthController::class, 'me']);

// 로그아웃
Route::post('/tillwhite/logout', [AuthController::class, 'logout']);


/**
 * Till White 업무 API
 *
 * auth 미들웨어를 적용하여
 * Laravel 세션 인증이 완료된 사용자만 접근할 수 있습니다.
 *
 * 아래 그룹 내부의 모든 주소에는
 * /tillwhite/api 접두사가 자동으로 적용됩니다.
 */
Route::middleware('auth')->prefix('tillwhite/api')->group(function () {
    /**
     * 생산·폐기 관리
     */

    // 생산·폐기·로스 기록 조회
    Route::get('/production', [ProductionRecordController::class, 'index']);

    // 생산 입력에 필요한 제품 및 작업자 목록 조회
    Route::get('/production/options', [ProductionRecordController::class, 'options']);

    // 생산·폐기·로스 기록 등록
    Route::post('/production', [ProductionRecordController::class, 'store']);

    // 생산·폐기·로스 기록 수정
    Route::put('/production/{productionRecord}', [ProductionRecordController::class, 'update']);

    // 생산·폐기·로스 기록 삭제
    Route::delete('/production/{productionRecord}', [ProductionRecordController::class, 'destroy']);


    /**
     * 근무 관리
     */

    // 근무 관리 화면에 필요한 데이터 조회
    Route::get('/work', [WorkController::class, 'index']);

    // 근무 스케줄 등록
    Route::post('/work/schedules', [WorkController::class, 'schedule']);

    // 휴가 신청
    Route::post('/work/leave', [WorkController::class, 'leave']);

    // 희망휴무 신청
    Route::post('/work/day-off', [WorkController::class, 'dayOff']);

    // 휴가 또는 희망휴무 신청 승인/반려
    Route::put('/work/requests/{type}/{id}', [WorkController::class, 'review']);


    /**
     * 제품·레시피 관리
     */

    // 제품 및 관련 정보 조회
    Route::get('/products', [ProductController::class, 'index']);

    // 신규 제품 등록
    Route::post('/products', [ProductController::class, 'store']);

    // 제품 사용/중지 상태 변경
    Route::put('/products/{product}/toggle', [ProductController::class, 'toggle']);

    // 제품 레시피 등록
    Route::post('/products/{product}/recipes', [ProductController::class, 'recipe']);


    /**
     * 매출 관리
     */

    // 매출 기록 조회
    Route::get('/sales', [SalesController::class, 'index']);

    // 신규 매출 등록
    Route::post('/sales', [SalesController::class, 'store']);


    /**
     * 직원 관리
     */

    // 직원 목록 및 등록에 필요한 정보 조회
    Route::get('/employees', [AdminController::class, 'employees']);

    // 신규 직원 등록
    Route::post('/employees', [AdminController::class, 'employeeStore']);

    // 직원 재직 상태 변경
    Route::put('/employees/{user}/status', [AdminController::class, 'employeeStatus']);


    /**
     * 점포 관리
     */

    // 점포 목록 조회
    Route::get('/stores', [AdminController::class, 'stores']);

    // 신규 점포 등록
    Route::post('/stores', [AdminController::class, 'storeStore']);


    /**
     * 시스템 관리
     */

    // 시스템 설정, 역할, 권한, 직급 정보 조회
    Route::get('/system', [AdminController::class, 'system']);

    // 시스템 설정 저장
    Route::post('/system/settings', [AdminController::class, 'setting']);

    // 특정 역할의 권한 구성 변경
    Route::put('/system/roles/{role}/permissions', [AdminController::class, 'rolePermissions']);


    /**
     * 감사 로그
     */

    // 감사 로그 조회
    Route::get('/audits', [AdminController::class, 'audits']);
});


/**
 * Vue Router용 SPA Catch-all Route
 *
 * /tillwhite 하위의 화면 주소는 tillwhite.blade.php를 반환하고,
 * 실제 화면 전환은 Vue Router가 처리합니다.
 */
Route::get('/tillwhite/{path?}', fn () => view('tillwhite'))->where('path', '.*');