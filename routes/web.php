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
 *
 * 인증 관련 기능은 현재 실제 사용하는 기능이므로
 * 모든 Route를 정상적으로 등록합니다.
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
 *
 * 현재 실제 사용하는 기능:
 * - 제품 관리
 * - 생산·폐기 관리
 *
 * 현재 개발 중인 기능:
 * - 근무 관리
 * - 매출 관리
 * - 직원 관리
 * - 점포 관리
 * - 시스템
 * - 감사 로그
 *
 * 개발 중인 기능의 Route 코드는 삭제하지 않고
 * 주석 상태로 보존합니다.
 *
 * 따라서 개발 중인 기능의 Controller로 연결되는
 * API Route는 현재 Laravel에 등록되지 않습니다.
 *
 * 나중에 기능 개발이 완료되면
 * 필요한 Route의 주석을 해제하여 다시 활성화할 수 있습니다.
 */
Route::middleware('auth')->prefix('tillwhite/api')->group(function () {
    /**
     * 제품 관리
     *
     * 현재 사용 중인 기능입니다.
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
     * 생산·폐기 관리
     *
     * 현재 사용 중인 기능입니다.
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
     *
     * 현재 개발 중인 기능입니다.
     *
     * 기능 개발이 완료되면
     * 아래 Route의 주석을 해제합니다.
     */

    // Route::get('/work', [WorkController::class, 'index']);
    // Route::post('/work/schedules', [WorkController::class, 'schedule']);
    // Route::post('/work/leave', [WorkController::class, 'leave']);
    // Route::post('/work/day-off', [WorkController::class, 'dayOff']);
    // Route::put('/work/requests/{type}/{id}', [WorkController::class, 'review']);


    /**
     * 매출 관리
     *
     * 현재 개발 중인 기능입니다.
     *
     * 기능 개발이 완료되면
     * 아래 Route의 주석을 해제합니다.
     */

    // Route::get('/sales', [SalesController::class, 'index']);
    // Route::post('/sales', [SalesController::class, 'store']);


    /**
     * 직원 관리
     *
     * 현재 개발 중인 기능입니다.
     *
     * 기능 개발이 완료되면
     * 아래 Route의 주석을 해제합니다.
     */

    Route::get('/employees', [AdminController::class, 'employees']);
    Route::post('/employees', [AdminController::class, 'employeeStore']);
    Route::put('/employees/{user}/status', [AdminController::class, 'employeeStatus']);


    /**
     * 점포 관리
     *
     * 현재 개발 중인 기능입니다.
     *
     * 기능 개발이 완료되면
     * 아래 Route의 주석을 해제합니다.
     */

    // Route::get('/stores', [AdminController::class, 'stores']);
    // Route::post('/stores', [AdminController::class, 'storeStore']);


    /**
     * 시스템 관리
     *
     * 현재 개발 중인 기능입니다.
     *
     * 기능 개발이 완료되면
     * 아래 Route의 주석을 해제합니다.
     */

    // Route::get('/system', [AdminController::class, 'system']);
    // Route::post('/system/settings', [AdminController::class, 'setting']);
    // Route::put('/system/roles/{role}/permissions', [AdminController::class, 'rolePermissions']);


    /**
     * 감사 로그
     *
     * 현재 개발 중인 기능입니다.
     *
     * 기능 개발이 완료되면
     * 아래 Route의 주석을 해제합니다.
     */

    // Route::get('/audits', [AdminController::class, 'audits']);
});


/**
 * 존재하지 않는 Till White API 차단
 *
 * 위에서 정상적으로 등록된 API와 일치하지 않는
 * /tillwhite/api/* 요청은 모두 404 Not Found로 처리합니다.
 *
 * 예:
 * - /tillwhite/api/work
 * - /tillwhite/api/sales
 * - /tillwhite/api/employees
 * - /tillwhite/api/stores
 * - /tillwhite/api/system
 * - /tillwhite/api/audits
 *
 * 개발 중인 API뿐만 아니라 존재하지 않는 잘못된 API 주소도
 * Vue SPA 화면으로 넘어가지 않고 여기에서 차단됩니다.
 *
 * 이 Route는 반드시
 * 정상 업무 API보다 아래에 위치해야 합니다.
 */
Route::any('/tillwhite/api/{path?}', fn () => abort(404))->where('path', '.*');


/**
 * Vue Router용 SPA Catch-all Route
 *
 * /tillwhite 하위의 화면 주소는
 * tillwhite.blade.php를 반환합니다.
 *
 * 이후 실제 화면 전환은 Vue Router가 처리합니다.
 *
 * /tillwhite/api/* 주소는 바로 위의 API 차단 Route에서
 * 먼저 처리하기 때문에 이 SPA Route까지 내려오지 않습니다.
 *
 * 반드시 모든 Till White API Route보다
 * 가장 아래에 위치해야 합니다.
 */
Route::get('/tillwhite/{path?}', fn () => view('tillwhite'))->where('path', '.*');