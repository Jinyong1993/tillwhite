<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 기본 Queue 연결
    |--------------------------------------------------------------------------
    |
    | Laravel에서 Job을 Queue로 보낼 때
    | 별도의 연결 방식을 지정하지 않으면 사용할 기본 설정입니다.
    |
    | 현재 Till White는 database Queue를 기본값으로 사용합니다.
    |
    | .env:
    |
    | QUEUE_CONNECTION=database
    |
    | database Queue는 실행할 작업을 jobs 테이블에 저장한 뒤
    | Queue Worker가 해당 작업을 순서대로 처리합니다.
    |
    | 추후 다음과 같이 시간이 걸리는 작업을
    | 요청 처리와 분리할 때 사용할 수 있습니다.
    |
    | - 이메일 발송
    | - 대량 데이터 처리
    | - 엑셀 및 보고서 생성
    | - 이미지 후처리
    | - 각종 백그라운드 작업
    |
    */
    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue 연결 설정
    |--------------------------------------------------------------------------
    |
    | Laravel에서 사용할 수 있는 Queue 처리 방식을 정의합니다.
    |
    | 현재 Till White:
    |
    | database
    | - 기본 Queue 방식
    | - Job을 DB의 jobs 테이블에 저장
    |
    | 나머지 연결 방식은 현재 사용하지 않지만
    | Laravel 기본 구성이므로 추후 확장을 위해 유지합니다.
    |
    */
    'connections' => [

        /*
        |--------------------------------------------------------------------------
        | Sync Queue
        |--------------------------------------------------------------------------
        |
        | Job을 Queue에 보관하지 않고
        | 현재 요청 안에서 즉시 실행합니다.
        |
        | 별도의 Queue Worker가 필요하지 않습니다.
        |
        | 간단한 개발이나 테스트에는 편리하지만
        | 시간이 오래 걸리는 작업은 사용자 요청 자체를
        | 느리게 만들 수 있습니다.
        |
        */
        'sync' => [
            'driver' => 'sync',
        ],

        /*
        |--------------------------------------------------------------------------
        | Database Queue
        |--------------------------------------------------------------------------
        |
        | Job을 데이터베이스의 jobs 테이블에 저장합니다.
        |
        | 현재 Till White의 기본 Queue 방식입니다.
        |
        | table
        | - 대기 중인 Job을 저장할 테이블
        |
        | queue
        | - 사용할 Queue 이름
        |
        | retry_after
        | - 처리 중인 Job을 다시 시도 가능한 상태로 판단하기까지의 시간
        |
        | after_commit
        | - DB Transaction이 Commit된 이후에만
        |   Job을 Queue에 보낼지 여부
        |
        | 실제 비동기 처리를 위해서는 Queue Worker가 필요합니다.
        |
        | 예:
        |
        | php artisan queue:work
        |
        */
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env(
                'DB_QUEUE_RETRY_AFTER',
                90
            ),
            'after_commit' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Beanstalkd Queue
        |--------------------------------------------------------------------------
        |
        | Beanstalkd Queue 서버를 사용할 경우의 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        */
        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env(
                'BEANSTALKD_QUEUE_HOST',
                'localhost'
            ),
            'queue' => env(
                'BEANSTALKD_QUEUE',
                'default'
            ),
            'retry_after' => (int) env(
                'BEANSTALKD_QUEUE_RETRY_AFTER',
                90
            ),
            'block_for' => 0,
            'after_commit' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Amazon SQS Queue
        |--------------------------------------------------------------------------
        |
        | AWS Simple Queue Service를 이용하여
        | Queue를 처리할 경우의 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        | AWS 인증 정보는 코드에 직접 작성하지 않고
        | .env에서 관리합니다.
        |
        */
        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env(
                'SQS_PREFIX',
                'https://sqs.us-east-1.amazonaws.com/your-account-id'
            ),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env(
                'AWS_DEFAULT_REGION',
                'us-east-1'
            ),
            'after_commit' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Redis Queue
        |--------------------------------------------------------------------------
        |
        | Redis를 이용하여 Queue를 처리할 경우의 설정입니다.
        |
        | DB를 계속 조회하는 방식보다 높은 처리량이 필요한
        | 운영 환경에서 사용할 수 있습니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        */
        'redis' => [
            'driver' => 'redis',
            'connection' => env(
                'REDIS_QUEUE_CONNECTION',
                'default'
            ),
            'queue' => env(
                'REDIS_QUEUE',
                'default'
            ),
            'retry_after' => (int) env(
                'REDIS_QUEUE_RETRY_AFTER',
                90
            ),
            'block_for' => null,
            'after_commit' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Deferred Queue
        |--------------------------------------------------------------------------
        |
        | 현재 요청의 응답이 만들어진 이후
        | Job을 지연 실행하는 방식입니다.
        |
        | 별도의 외부 Queue 저장소가 필요하지 않는
        | 간단한 작업에 사용할 수 있습니다.
        |
        | 현재 Till White에서는 기본 Queue로 사용하지 않습니다.
        |
        */
        'deferred' => [
            'driver' => 'deferred',
        ],

        /*
        |--------------------------------------------------------------------------
        | Background Queue
        |--------------------------------------------------------------------------
        |
        | Job을 백그라운드 프로세스로 실행하기 위한
        | Queue 방식입니다.
        |
        | 현재 Till White에서는 기본 Queue로 사용하지 않습니다.
        |
        */
        'background' => [
            'driver' => 'background',
        ],

        /*
        |--------------------------------------------------------------------------
        | Failover Queue
        |--------------------------------------------------------------------------
        |
        | 첫 번째 Queue 연결을 사용할 수 없는 경우
        | 다음 연결 방식으로 작업을 처리합니다.
        |
        | 현재 순서:
        |
        | 1. database
        | 2. deferred
        |
        */
        'failover' => [
            'driver' => 'failover',

            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batch 설정
    |--------------------------------------------------------------------------
    |
    | 여러 Job을 하나의 묶음(Batch)으로 실행할 때
    | 해당 Batch의 진행 상태를 저장하는 설정입니다.
    |
    | 예:
    |
    | 대량 보고서 생성
    | → 여러 개의 Job 실행
    | → 전체 진행 상태를 하나의 Batch로 관리
    |
    | 현재 Till White의 기본 DB가 SQLite이므로
    | 별도 설정이 없다면 SQLite를 사용합니다.
    |
    | 관련 테이블:
    |
    | job_batches
    |
    */
    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | 실패한 Queue Job
    |--------------------------------------------------------------------------
    |
    | Queue Worker가 Job을 정상적으로 처리하지 못하고
    | 최종적으로 실패한 경우 해당 정보를 저장합니다.
    |
    | 기본 저장 방식:
    |
    | database-uuids
    |
    | 관련 테이블:
    |
    | failed_jobs
    |
    | 실패한 Job을 기록해두면 운영 중 문제가 발생했을 때
    | 어떤 작업이 실패했는지 확인할 수 있습니다.
    |
    | 현재 Till White의 기본 DB가 SQLite이므로
    | 별도 설정이 없다면 SQLite에 저장합니다.
    |
    */
    'failed' => [
        'driver' => env(
            'QUEUE_FAILED_DRIVER',
            'database-uuids'
        ),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];