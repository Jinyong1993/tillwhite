<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Laravel 실행 시작 시간 기록
|--------------------------------------------------------------------------
|
| Laravel 애플리케이션의 실행이 시작된 시간을 기록합니다.
|
| 프레임워크 내부에서 요청 처리 시간이나
| 성능 측정 등에 사용할 수 있습니다.
|
*/
define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Maintenance Mode 확인
|--------------------------------------------------------------------------
|
| 애플리케이션이 점검 모드인지 확인합니다.
|
| 예를 들어 다음 명령으로 점검 모드를 활성화하면:
|
| php artisan down
|
| storage/framework/maintenance.php 파일이 생성되고,
| 일반적인 애플리케이션 실행 대신
| 점검 모드 응답을 처리하게 됩니다.
|
| 다시 정상 운영 상태로 변경할 때는:
|
| php artisan up
|
| 명령을 사용할 수 있습니다.
|
*/
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Composer Autoloader 등록
|--------------------------------------------------------------------------
|
| Composer가 생성한 Autoloader를 불러옵니다.
|
| 이를 통해 Laravel Framework와
| app 디렉터리의 클래스, 설치된 Composer 패키지 등을
| 필요한 위치에서 자동으로 불러올 수 있습니다.
|
*/
require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Laravel 애플리케이션 부팅
|--------------------------------------------------------------------------
|
| bootstrap/app.php를 불러와
| Laravel Application 객체를 생성합니다.
|
| bootstrap/app.php에는 다음과 같은
| 애플리케이션 초기 설정이 들어 있습니다.
|
| - Web Route 등록
| - Console Route 등록
| - Health Check Route 등록
| - Middleware 설정
| - Exception 설정
|
*/
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| HTTP 요청 처리
|--------------------------------------------------------------------------
|
| 현재 브라우저에서 들어온 HTTP 요청을
| Laravel Request 객체로 생성합니다.
|
| 이후 Laravel이 해당 요청을
|
| Route
| → Middleware
| → Controller
| → Response
|
| 순서로 처리하여 최종 응답을 브라우저에 반환합니다.
|
| Till White의 예:
|
| POST /tillwhite/login
| → AuthController
|
| GET /tillwhite/api/production
| → ProductionRecordController
|
*/
$app->handleRequest(Request::capture());