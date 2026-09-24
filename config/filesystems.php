<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 기본 파일 저장소
    |--------------------------------------------------------------------------
    |
    | Laravel에서 파일을 저장할 때 별도의 Disk를 지정하지 않으면
    | 기본적으로 사용할 파일 저장소를 설정합니다.
    |
    | Till White는 기본값으로 local Disk를 사용합니다.
    |
    | 실제 값은 .env에서 변경할 수 있습니다.
    |
    | FILESYSTEM_DISK=local
    |
    | local Disk는 외부 사용자에게 직접 공개하지 않는
    | 내부 파일을 저장하는 용도로 사용합니다.
    |
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | 파일 저장소(Disk) 설정
    |--------------------------------------------------------------------------
    |
    | Laravel에서는 파일 저장 위치를 Disk라는 단위로 관리합니다.
    |
    | 현재 설정된 Disk:
    |
    | local
    | - 서버 내부의 비공개 파일 저장
    |
    | public
    | - 브라우저에서 접근할 수 있는 공개 파일 저장
    |
    | s3
    | - AWS S3 또는 S3 호환 클라우드 저장소
    |
    | 파일의 용도에 따라 적절한 Disk를 선택하여 사용합니다.
    |
    */
    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | Local Disk
        |--------------------------------------------------------------------------
        |
        | 외부에 직접 공개하지 않는 파일을 저장합니다.
        |
        | 실제 저장 위치:
        |
        | storage/app/private
        |
        | 예:
        | - 내부 관리용 파일
        | - 외부에서 직접 접근하면 안 되는 자료
        | - 임시 생성 파일
        |
        | 공개 이미지처럼 브라우저에서 직접 접근해야 하는 파일은
        | local이 아니라 public Disk를 사용하는 것이 적절합니다.
        |
        */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Public Disk
        |--------------------------------------------------------------------------
        |
        | 브라우저에서 직접 접근할 수 있어야 하는
        | 공개 파일을 저장합니다.
        |
        | 실제 저장 위치:
        |
        | storage/app/public
        |
        | 공개 URL:
        |
        | {APP_URL}/storage/...
        |
        | 예:
        | 레시피 이미지처럼 직원 화면에서 표시해야 하는 파일은
        | public Disk를 사용할 수 있습니다.
        |
        | public Disk의 파일을 웹에서 접근하려면
        | public/storage와 storage/app/public을 연결하는
        | Symbolic Link가 필요합니다.
        |
        | 최초 설정 시:
        |
        | php artisan storage:link
        |
        */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(
                env('APP_URL', 'http://localhost'),
                '/'
            ).'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Amazon S3 Disk
        |--------------------------------------------------------------------------
        |
        | AWS S3 또는 S3 호환 스토리지를
        | 파일 저장소로 사용할 경우의 설정입니다.
        |
        | 현재 Till White에서는 사용하지 않습니다.
        |
        | 추후 서버를 분리하거나 이미지 및 파일의 양이 많아져
        | 외부 스토리지가 필요한 경우 사용할 수 있습니다.
        |
        | 접속 정보는 코드에 직접 작성하지 않고
        | .env에서 관리합니다.
        |
        | AWS_ACCESS_KEY_ID
        | AWS_SECRET_ACCESS_KEY
        | AWS_DEFAULT_REGION
        | AWS_BUCKET
        | AWS_URL
        | AWS_ENDPOINT
        |
        */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env(
                'AWS_USE_PATH_STYLE_ENDPOINT',
                false
            ),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Link 설정
    |--------------------------------------------------------------------------
    |
    | storage/app/public에 저장된 파일을
    | 웹 브라우저에서 접근할 수 있도록 연결합니다.
    |
    | 다음 Artisan 명령을 실행하면:
    |
    | php artisan storage:link
    |
    | 아래와 같은 연결이 생성됩니다.
    |
    | public/storage
    |     ↓
    | storage/app/public
    |
    | 따라서 public Disk에 다음 파일이 있다면:
    |
    | storage/app/public/recipes/bread.jpg
    |
    | 브라우저에서는 다음과 같은 경로로 접근할 수 있습니다.
    |
    | /storage/recipes/bread.jpg
    |
    */
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];