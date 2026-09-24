<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | 기본 로그 채널
    |--------------------------------------------------------------------------
    |
    | Laravel에서 로그를 기록할 때 별도의 채널을 지정하지 않으면
    | 기본적으로 사용할 로그 채널입니다.
    |
    | Till White는 기본적으로 stack 채널을 사용합니다.
    |
    | 실제 값은 .env에서 변경할 수 있습니다.
    |
    | LOG_CHANNEL=stack
    |
    | stack 채널은 아래에 설정된 여러 로그 채널을
    | 하나로 묶어서 사용할 수 있도록 합니다.
    |
    */
    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecated 기능 경고 로그
    |--------------------------------------------------------------------------
    |
    | PHP, Laravel 또는 외부 라이브러리에서
    | 앞으로 제거될 예정인 기능을 사용했을 때 발생하는
    | Deprecated 경고를 어디에 기록할지 설정합니다.
    |
    | 현재 기본값은 null이므로 별도로 기록하지 않습니다.
    |
    | 추후 Laravel 또는 PHP 버전을 업그레이드하기 전에
    | Deprecated 기능을 확인하고 싶다면
    | 별도의 로그 채널을 지정할 수 있습니다.
    |
    | trace가 true이면 Deprecated 경고가 발생한
    | 호출 경로까지 함께 기록할 수 있습니다.
    |
    */
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | 로그 채널 설정
    |--------------------------------------------------------------------------
    |
    | Laravel에서 사용할 수 있는 로그 저장 방식을 정의합니다.
    |
    | Laravel은 내부적으로 Monolog를 사용하여
    | 로그를 기록합니다.
    |
    | Till White에서 일반적으로 확인하게 될 로그는
    | storage/logs 디렉터리에 저장됩니다.
    |
    | 주의:
    |
    | 이 Laravel 로그와 audit_logs 테이블은 목적이 다릅니다.
    |
    | Laravel Log
    | - 프로그램 오류
    | - 예외
    | - 디버깅 정보
    | - 서버 및 시스템 문제 추적
    |
    | AuditLog
    | - 어떤 직원이 어떤 업무 데이터를 변경했는지 추적
    | - 변경 전/후 값 기록
    | - 업무 변경 이력 관리
    |
    */
    'channels' => [

        /*
        |--------------------------------------------------------------------------
        | Stack 채널
        |--------------------------------------------------------------------------
        |
        | 여러 로그 채널을 하나로 묶어서 사용하는 채널입니다.
        |
        | 현재 기본 설정에서는 single 채널을 사용합니다.
        |
        | .env 예:
        |
        | LOG_STACK=single
        |
        | 필요하면 여러 채널을 쉼표로 구분하여
        | 동시에 사용할 수도 있습니다.
        |
        */
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(
                ',',
                (string) env('LOG_STACK', 'single')
            ),
            'ignore_exceptions' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Single 채널
        |--------------------------------------------------------------------------
        |
        | 모든 로그를 하나의 파일에 계속 기록합니다.
        |
        | 저장 위치:
        |
        | storage/logs/laravel.log
        |
        | 현재 개발 환경에서 오류를 확인할 때
        | 가장 자주 보게 되는 로그 파일입니다.
        |
        | LOG_LEVEL을 통해 기록할 최소 로그 수준을
        | .env에서 변경할 수 있습니다.
        |
        */
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Daily 채널
        |--------------------------------------------------------------------------
        |
        | 로그 파일을 날짜별로 나누어 저장합니다.
        |
        | 오래 운영하는 실제 서버에서는 하나의 laravel.log 파일이
        | 계속 커지는 것을 방지할 수 있어 유용합니다.
        |
        | LOG_DAILY_DAYS는 로그 파일을
        | 며칠 동안 보관할지 설정합니다.
        |
        | 기본값:
        | 14일
        |
        */
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Slack 채널
        |--------------------------------------------------------------------------
        |
        | 심각한 오류가 발생했을 때
        | Slack Webhook으로 로그 알림을 보낼 수 있습니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        | 기본 로그 수준은 critical이므로
        | 심각한 오류만 전송하도록 설정되어 있습니다.
        |
        | Webhook 주소 등의 정보는
        | .env에서 관리합니다.
        |
        */
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env(
                'LOG_SLACK_USERNAME',
                env('APP_NAME', 'Till White')
            ),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Papertrail 채널
        |--------------------------------------------------------------------------
        |
        | Papertrail 같은 외부 로그 관리 서비스를
        | 사용할 경우의 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        */
        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env(
                'LOG_PAPERTRAIL_HANDLER',
                SyslogUdpHandler::class
            ),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'
                    .env('PAPERTRAIL_URL')
                    .':'
                    .env('PAPERTRAIL_PORT'),
            ],
            'processors' => [
                PsrLogMessageProcessor::class,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | STDERR 채널
        |--------------------------------------------------------------------------
        |
        | 로그를 PHP의 표준 오류 출력(stderr)으로 보냅니다.
        |
        | Docker, 컨테이너 또는 일부 서버 환경에서
        | 로그를 외부 시스템으로 전달할 때 사용할 수 있습니다.
        |
        | 현재 일반적인 Till White 로컬 개발에서는
        | 기본 로그 채널로 사용하지 않습니다.
        |
        */
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [
                PsrLogMessageProcessor::class,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Syslog 채널
        |--------------------------------------------------------------------------
        |
        | 운영체제의 Syslog 시스템으로
        | Laravel 로그를 전달할 때 사용합니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        */
        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env(
                'LOG_SYSLOG_FACILITY',
                LOG_USER
            ),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Error Log 채널
        |--------------------------------------------------------------------------
        |
        | PHP 또는 서버 환경에서 제공하는
        | 기본 Error Log로 로그를 전달합니다.
        |
        */
        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Null 채널
        |--------------------------------------------------------------------------
        |
        | 전달된 로그를 실제로 저장하지 않습니다.
        |
        | 로그를 의도적으로 무시해야 하는 경우 사용합니다.
        |
        | 현재 Deprecated 경고 로그의 기본 채널로
        | 사용되고 있습니다.
        |
        */
        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        /*
        |--------------------------------------------------------------------------
        | Emergency 채널
        |--------------------------------------------------------------------------
        |
        | Laravel이 설정된 로그 채널을 정상적으로
        | 사용할 수 없는 상황에서 사용하는 비상 로그입니다.
        |
        | 저장 위치:
        |
        | storage/logs/laravel.log
        |
        */
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];