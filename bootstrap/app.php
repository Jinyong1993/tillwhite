<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        // Till White API에서는 프레임워크 기본 영문 오류를 사용자에게 직접 노출하지 않습니다.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('tillwhite/api/*')) {
                return null;
            }

            return response()->json([
                'message' => '로그인이 필요합니다. 다시 로그인해주세요.',
            ], 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('tillwhite/api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = trim($exception->getMessage());

            // Controller에서 의도적으로 작성한 한글 안내는 그대로 보존합니다.
            if ($message !== '' && preg_match('/[가-힣]/u', $message)) {
                return response()->json(['message' => $message], $status);
            }

            $messages = [
                400 => '요청 내용을 확인해주세요.',
                401 => '로그인이 필요합니다. 다시 로그인해주세요.',
                403 => '이 작업을 수행할 권한이 없습니다.',
                404 => '요청한 정보를 찾을 수 없습니다.',
                405 => '지원하지 않는 요청입니다.',
                409 => '현재 상태에서는 요청을 처리할 수 없습니다.',
                419 => '로그인 세션이 만료되었습니다. 다시 로그인해주세요.',
                429 => '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.',
                500 => '서버에서 오류가 발생했습니다. 잠시 후 다시 시도해주세요.',
            ];

            return response()->json([
                'message' => $messages[$status] ?? '요청을 처리하지 못했습니다.',
            ], $status);
        });
    })

    /**
     * 위 설정을 기반으로
     * Laravel 애플리케이션을 생성합니다.
     */
    ->create();