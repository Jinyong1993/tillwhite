<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 애플리케이션 서비스 등록
     *
     * 서비스 컨테이너에 별도로 등록해야 하는
     * 서비스가 생기면 이곳에서 설정합니다.
     */
    public function register(): void
    {
        //
    }

    /**
     * 애플리케이션 공통 초기 설정
     *
     * Laravel 애플리케이션이 시작될 때
     * 전체 시스템에 공통으로 적용할 설정을 작성합니다.
     */
    public function boot(): void
    {
        /**
         * 운영 환경에서 위험한 데이터베이스 명령을
         * 실행할 때 추가 확인 절차를 적용합니다.
         *
         * migrate:fresh, db:wipe 등으로 인해
         * 운영 데이터가 실수로 삭제되는 것을 방지합니다.
         */
        DB::prohibitDestructiveCommands(app()->isProduction());
    }
}