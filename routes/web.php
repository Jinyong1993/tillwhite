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
 * 인증 미들웨어(auth)를 적용하여
 * Laravel 세션 인증이 완료된 사용자만 접근할 수 있습니다.
 *
 * 아래 그룹 내부의 모든 주소에는
 * /tillwhite/api 접두사가 자동으로 적용됩니다.
 *
 * 현재 실제 사용하는 기능:
 * - 제품 관리
 * - 생산·폐기 관리
 * - 직원 관리
 *
 * 현재 개발 중인 기능:
 * - 근무 관리
 * - 매출 관리
 * - 점포 관리
 * - 시스템
 * - 감사 로그
 *
 * 개발 중인 기능의 Route 코드는 삭제하지 않고
 * 주석 상태로 보존합니다.
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

    // 오늘 생산·폐기·로스 현황 조회
    Route::get('/production/summary', [ProductionRecordController::class, 'summary']);

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
     */

    // Route::get('/sales', [SalesController::class, 'index']);
    // Route::post('/sales', [SalesController::class, 'store']);


    /**
     * 직원 관리
     *
     * 현재 사용 중인 기능입니다.
     *
     * 화면에서 버튼의 활성/비활성 상태만 신뢰하지 않고
     * 실제 동작 직전에 Laravel 서버에서 권한을 다시 확인합니다.
     */

    // 직원 목록 조회
    Route::get('/employees', [AdminController::class, 'employees']);

    /**
     * 직원 등록 화면 접근 확인
     *
     * 직원 등록 버튼을 누르면 등록 다이얼로그를 열기 전에
     * 직원 관리 권한(employee.manage)을 서버에서 확인합니다.
     *
     * 권한 확인이 완료되면 등록에 필요한 선택 목록과
     * 현재 로그인 세션의 임시저장 내용(draft)을 반환합니다.
     */
    Route::get('/employees/create', [AdminController::class, 'employeeCreate']);

    /**
     * 직원 등록 내용 임시저장
     *
     * 작성 중인 직원 등록 내용을
     * 현재 로그인 사용자의 Laravel Session에 저장합니다.
     *
     * 직원 관리 권한(employee.manage)을 서버에서 확인하며
     * 비밀번호(password)는 임시저장하지 않습니다.
     *
     * /employees/{user}보다 위에 위치시켜
     * "draft"가 직원 번호(user)로 해석되지 않도록 합니다.
     */
    Route::put('/employees/draft', [AdminController::class, 'employeeDraft']);

    /**
     * 직원 등록 임시저장 내용 전체 삭제
     *
     * 직원 등록 화면에서 "전체 삭제"를 선택했을 때
     * 현재 로그인 사용자의 Laravel Session에 저장되어 있는
     * 직원 등록 임시저장 내용(draft)을 삭제합니다.
     *
     * 실제 등록된 직원 데이터는 삭제하지 않으며
     * 아직 등록되지 않은 작성 중인 내용만 삭제합니다.
     *
     * 직원 관리 권한(employee.manage)을
     * Laravel 서버에서 다시 확인합니다.
     *
     * 비밀번호(password)는 원래 임시저장 대상이 아니므로
     * 서버에서 삭제할 임시 비밀번호 데이터는 없습니다.
     *
     * /employees/{user}보다 위에 위치시켜
     * "draft"가 직원 번호(user)로 해석되지 않도록 합니다.
     */
    Route::delete('/employees/draft', [AdminController::class, 'employeeDraftDelete']);

    /**
     * 직원 상세정보 조회
     *
     * 상세보기 버튼을 누르면 다이얼로그를 열기 전에
     * 직원 조회 권한(employee.view)과
     * 해당 직원에 대한 조회 가능 범위를 서버에서 확인합니다.
     *
     * 검사가 통과하면 해당 직원의 최신 정보를 반환합니다.
     */
    Route::get('/employees/{user}', [AdminController::class, 'employeeShow']);

    // 신규 직원 등록
    Route::post('/employees', [AdminController::class, 'employeeStore']);

    /**
     * 직원 재직 상태 변경
     *
     * 실제 저장 시 직원 관리 권한(employee.manage)을
     * Laravel 서버에서 다시 확인합니다.
     */
    Route::put('/employees/{user}/status', [AdminController::class, 'employeeStatus']);


    /**
     * 점포 관리
     *
     * 현재 개발 중인 기능입니다.
     */

    // Route::get('/stores', [AdminController::class, 'stores']);
    // Route::post('/stores', [AdminController::class, 'storeStore']);


    /**
     * 시스템 관리
     *
     * 현재 개발 중인 기능입니다.
     */

    // Route::get('/system', [AdminController::class, 'system']);
    // Route::post('/system/settings', [AdminController::class, 'setting']);
    // Route::put('/system/roles/{role}/permissions', [AdminController::class, 'rolePermissions']);


    /**
     * 감사 로그
     *
     * 현재 개발 중인 기능입니다.
     */

    // Route::get('/audits', [AdminController::class, 'audits']);
});


/**
 * 존재하지 않는 Till White API 차단
 *
 * 위에서 정상적으로 등록된 API와 일치하지 않는
 * /tillwhite/api/* 요청은 모두 404 Not Found로 처리합니다.
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