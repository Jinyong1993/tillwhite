<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

// 인증 API
Route::post('/tillwhite/login',[AuthController::class,'login']);
Route::get('/tillwhite/auth/me',[AuthController::class,'me']);
Route::post('/tillwhite/logout',[AuthController::class,'logout']);

// 로그인 세션이 필요한 업무 API
Route::middleware('auth')->prefix('tillwhite/api')->group(function(){
    Route::get('/production',[ProductionRecordController::class,'index']);
    Route::get('/production/options',[ProductionRecordController::class,'options']);
    Route::post('/production',[ProductionRecordController::class,'store']);
    Route::put('/production/{productionRecord}',[ProductionRecordController::class,'update']);
    Route::delete('/production/{productionRecord}',[ProductionRecordController::class,'destroy']);

    Route::get('/work',[WorkController::class,'index']);
    Route::post('/work/schedules',[WorkController::class,'schedule']);
    Route::post('/work/leave',[WorkController::class,'leave']);
    Route::post('/work/day-off',[WorkController::class,'dayOff']);
    Route::put('/work/requests/{type}/{id}',[WorkController::class,'review']);

    Route::get('/products',[ProductController::class,'index']);
    Route::post('/products',[ProductController::class,'store']);
    Route::put('/products/{product}/toggle',[ProductController::class,'toggle']);
    Route::post('/products/{product}/recipes',[ProductController::class,'recipe']);

    Route::get('/sales',[SalesController::class,'index']);
    Route::post('/sales',[SalesController::class,'store']);

    Route::get('/employees',[AdminController::class,'employees']);
    Route::post('/employees',[AdminController::class,'employeeStore']);
    Route::put('/employees/{user}/status',[AdminController::class,'employeeStatus']);
    Route::get('/stores',[AdminController::class,'stores']);
    Route::post('/stores',[AdminController::class,'storeStore']);
    Route::get('/system',[AdminController::class,'system']);
    Route::post('/system/settings',[AdminController::class,'setting']);
    Route::put('/system/roles/{role}/permissions',[AdminController::class,'rolePermissions']);
    Route::get('/audits',[AdminController::class,'audits']);
});

// Vue Router용 SPA catch-all
Route::get('/tillwhite/{path?}',fn()=>view('tillwhite'))->where('path','.*');
