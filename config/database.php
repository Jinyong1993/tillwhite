<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | 기본 데이터베이스 연결
    |--------------------------------------------------------------------------
    |
    | Laravel에서 별도의 DB 연결을 지정하지 않았을 때
    | 기본적으로 사용할 데이터베이스를 설정합니다.
    |
    | Till White는 현재 SQLite를 사용합니다.
    |
    | 실제 값은 .env의 DB_CONNECTION에서 관리합니다.
    |
    | 현재 권장 설정:
    |
    | DB_CONNECTION=sqlite
    |
    */
    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | 데이터베이스 연결 설정
    |--------------------------------------------------------------------------
    |
    | Laravel에서 사용할 수 있는 데이터베이스 연결 정보를
    | 각각 정의합니다.
    |
    | 현재 Till White:
    |
    | SQLite
    | - 실제 사용
    |
    | MySQL
    | - 현재 사용하지 않음
    |
    | MariaDB
    | - 현재 사용하지 않음
    |
    | PostgreSQL
    | - 현재 사용하지 않음
    |
    | SQL Server
    | - 현재 사용하지 않음
    |
    | 현재 사용하지 않는 DB 설정도 Laravel 기본 구성이므로
    | 추후 DB 변경 가능성을 고려하여 그대로 유지합니다.
    |
    */
    'connections' => [

        /*
        |--------------------------------------------------------------------------
        | SQLite
        |--------------------------------------------------------------------------
        |
        | 현재 Till White에서 실제로 사용하는 데이터베이스입니다.
        |
        | 기본 DB 파일:
        |
        | database/database.sqlite
        |
        | SQLite는 별도의 DB 서버를 실행하지 않고
        | 하나의 파일에 데이터를 저장하기 때문에
        | 현재 개발 환경에서 간단하게 사용할 수 있습니다.
        |
        | foreign_key_constraints:
        | - 외래키 제약조건 사용 여부
        | - 기본값 true
        |
        | transaction_mode:
        | - SQLite 트랜잭션 시작 방식
        | - 현재 Laravel 기본값인 DEFERRED 사용
        |
        */
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env(
                'DB_DATABASE',
                database_path('database.sqlite')
            ),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        /*
        |--------------------------------------------------------------------------
        | MySQL
        |--------------------------------------------------------------------------
        |
        | MySQL을 사용할 경우의 연결 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        | 추후 실제 운영 환경에서 MySQL로 변경할 경우
        | .env의 DB_CONNECTION 및 DB_* 값을 변경하여
        | 이 설정을 사용할 수 있습니다.
        |
        | utf8mb4를 사용하여 한글 및 다양한 Unicode 문자를
        | 정상적으로 저장할 수 있도록 구성되어 있습니다.
        |
        */
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,

            /**
             * MySQL SSL 연결 옵션
             *
             * pdo_mysql 확장이 활성화되어 있을 때만 적용합니다.
             */
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500
                    ? Mysql::ATTR_SSL_CA
                    : PDO::MYSQL_ATTR_SSL_CA
                ) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
        |--------------------------------------------------------------------------
        | MariaDB
        |--------------------------------------------------------------------------
        |
        | MariaDB를 사용할 경우의 연결 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        | MySQL과 유사하게 utf8mb4 문자셋을 사용하며,
        | 필요할 경우 .env 설정을 통해 사용할 수 있습니다.
        |
        */
        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,

            /**
             * MariaDB SSL 연결 옵션
             */
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500
                    ? Mysql::ATTR_SSL_CA
                    : PDO::MYSQL_ATTR_SSL_CA
                ) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
        |--------------------------------------------------------------------------
        | PostgreSQL
        |--------------------------------------------------------------------------
        |
        | PostgreSQL을 사용할 경우의 연결 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        */
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Microsoft SQL Server
        |--------------------------------------------------------------------------
        |
        | Microsoft SQL Server를 사용할 경우의 연결 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        */
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,

            // SQL Server에서 SSL 관련 설정이 필요한 경우 사용합니다.
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env(
            //     'DB_TRUST_SERVER_CERTIFICATE',
            //     'false'
            // ),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration 관리 테이블
    |--------------------------------------------------------------------------
    |
    | Laravel이 어떤 Migration을 이미 실행했는지
    | 기록하는 테이블을 설정합니다.
    |
    | 기본 테이블:
    |
    | migrations
    |
    | php artisan migrate 실행 시 Laravel은 이 테이블을 확인하여
    | 아직 실행되지 않은 Migration만 실행합니다.
    |
    | 따라서 migrations 테이블은 일반 업무 데이터가 아니라
    | DB 구조 변경 이력을 Laravel이 관리하기 위한 테이블입니다.
    |
    */
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis 연결 설정
    |--------------------------------------------------------------------------
    |
    | Redis는 메모리 기반의 빠른 Key-Value 저장소입니다.
    |
    | 캐시, 세션, Queue 등의 용도로 사용할 수 있습니다.
    |
    | 현재 Till White에서는 Redis를 사용하지 않지만,
    | 추후 시스템 규모가 커지거나 성능 개선이 필요한 경우
    | 사용할 수 있도록 Laravel 기본 설정을 유지합니다.
    |
    */
    'redis' => [

        /*
        | Redis 클라이언트
        |
        | 기본값으로 PHP Redis 확장인 phpredis를 사용합니다.
        */
        'client' => env('REDIS_CLIENT', 'phpredis'),

        /*
        | Redis 공통 설정
        */
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),

            /**
             * 다른 애플리케이션의 Redis Key와
             * 충돌하지 않도록 접두사를 추가합니다.
             */
            'prefix' => env(
                'REDIS_PREFIX',
                Str::slug(
                    (string) env('APP_NAME', 'Till White')
                ).'-database-'
            ),

            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        /*
        |--------------------------------------------------------------------------
        | 기본 Redis DB
        |--------------------------------------------------------------------------
        |
        | 일반적인 Redis 데이터 저장에 사용하는 연결입니다.
        |
        | 기본 Redis DB 번호:
        | 0
        |
        */
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env(
                'REDIS_BACKOFF_ALGORITHM',
                'decorrelated_jitter'
            ),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        /*
        |--------------------------------------------------------------------------
        | 캐시용 Redis DB
        |--------------------------------------------------------------------------
        |
        | Redis를 Laravel Cache 저장소로 사용할 경우
        | 사용하는 별도의 연결입니다.
        |
        | 기본 Redis DB 번호:
        | 1
        |
        | 일반 Redis 데이터와 캐시 데이터를
        | 서로 분리하여 관리할 수 있습니다.
        |
        */
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env(
                'REDIS_BACKOFF_ALGORITHM',
                'decorrelated_jitter'
            ),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];