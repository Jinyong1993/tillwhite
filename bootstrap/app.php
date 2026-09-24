<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Laravel 애플리케이션 기본 설정
 *
 * 애플리케이션이 시작될 때 필요한
 * 라우팅, 미들웨어, 예외 처리 등의
 * 핵심 구성을 등록합니다.
 *
 * Till White의 실제 업무 기능은
 * Controller, Service, Model 등에서 처리하며,
 * 이 파일은 애플리케이션 전체에 적용되는
 * 기반 설정을 담당합니다.
 */
return Application::configure(basePath: dirname(__DIR__))

    /**
     * 라우팅 설정
     *
     * web
     * - 일반 웹 및 Till White API 라우트를 등록합니다.
     * - routes/web.php 파일을 사용합니다.
     *
     * commands
     * - Artisan 콘솔 명령 관련 라우트를 등록합니다.
     * - routes/console.php 파일을 사용합니다.
     *
     * health
     * - 애플리케이션 상태 확인용 URL입니다.
     * - /up 요청으로 Laravel 애플리케이션이
     *   정상적으로 실행 중인지 확인할 수 있습니다.
     */
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    /**
     * 전역 미들웨어 설정
     *
     * 모든 요청에 공통으로 적용하거나
     * Laravel의 기본 미들웨어 구성을 변경해야 할 경우
     * 이곳에서 설정합니다.
     *
     * 현재 Till White에서는 별도로 추가한
     * 전역 미들웨어 설정이 없습니다.
     */
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })

    /**
     * 전역 예외 처리 설정
     *
     * 애플리케이션에서 발생하는 예외를
     * 별도의 방식으로 처리하거나
     * 특정 예외의 응답 형식을 변경해야 할 경우
     * 이곳에서 설정합니다.
     *
     * 현재 Till White에서는 Laravel의
     * 기본 예외 처리 방식을 사용합니다.
     */
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })

    /**
     * 위 설정을 기반으로
     * Laravel 애플리케이션을 생성합니다.
     */
    ->create();