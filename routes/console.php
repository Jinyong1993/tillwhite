<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/**
 * Laravel 기본 예제 Artisan 명령어입니다.
 *
 * 터미널에서 아래 명령어를 실행하면
 * Laravel에 포함된 문구 하나를 출력합니다.
 *
 * php artisan inspire
 */
Artisan::command('inspire', function () {
    // Laravel에서 제공하는 문구를 콘솔에 출력
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');