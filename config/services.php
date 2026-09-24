<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 외부 서비스 설정
    |--------------------------------------------------------------------------
    |
    | Laravel에서 사용하는 외부 서비스의 인증 정보를
    | 한곳에서 관리하기 위한 설정 파일입니다.
    |
    | 예:
    |
    | - 이메일 발송 서비스
    | - AWS 서비스
    | - Slack 알림
    | - 기타 외부 API
    |
    | 중요한 API Key, Secret, Token 등의 실제 값은
    | 이 파일에 직접 작성하지 않고 .env에서 관리합니다.
    |
    | 예:
    |
    | POSTMARK_API_KEY=...
    | RESEND_API_KEY=...
    |
    | 애플리케이션에서는 필요할 경우 다음과 같이
    | 설정값을 가져올 수 있습니다.
    |
    | config('services.postmark.key')
    |
    | 현재 Till White에서는 아래 외부 서비스를
    | 직접 사용하고 있지 않지만,
    | 추후 기능 확장을 위해 Laravel 기본 설정을 유지합니다.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Postmark
    |--------------------------------------------------------------------------
    |
    | Postmark를 이용하여 이메일을 발송할 경우
    | 사용하는 API 인증 정보입니다.
    |
    | config/mail.php의 postmark Mailer와 연결하여
    | 사용할 수 있습니다.
    |
    | 현재 Till White에서는 사용하지 않습니다.
    |
    */
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resend
    |--------------------------------------------------------------------------
    |
    | Resend 서비스를 이용하여 이메일을 발송할 경우
    | 사용하는 API 인증 정보입니다.
    |
    | config/mail.php의 resend Mailer와 연결하여
    | 사용할 수 있습니다.
    |
    | 현재 Till White에서는 사용하지 않습니다.
    |
    */
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon SES
    |--------------------------------------------------------------------------
    |
    | AWS Simple Email Service(SES)를 이용하여
    | 이메일을 발송할 경우 사용하는 인증 정보입니다.
    |
    | AWS_ACCESS_KEY_ID
    | - AWS Access Key
    |
    | AWS_SECRET_ACCESS_KEY
    | - AWS Secret Key
    |
    | AWS_DEFAULT_REGION
    | - 사용할 AWS Region
    |
    | 실제 인증 정보는 반드시 .env에서 관리합니다.
    |
    | 현재 Till White에서는 사용하지 않습니다.
    |
    */
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env(
            'AWS_DEFAULT_REGION',
            'us-east-1'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    |
    | Slack Bot을 이용하여 시스템 알림을 전송할 경우
    | 사용하는 설정입니다.
    |
    | bot_user_oauth_token
    | - Slack Bot 인증 Token
    |
    | channel
    | - 기본 알림을 전송할 Slack Channel
    |
    | 예를 들어 추후 다음과 같은 알림 기능에
    | 활용할 수 있습니다.
    |
    | - 시스템 오류 알림
    | - 중요 업무 알림
    | - 관리자용 시스템 알림
    |
    | 현재 Till White에서는 사용하지 않습니다.
    |
    */
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env(
                'SLACK_BOT_USER_OAUTH_TOKEN'
            ),
            'channel' => env(
                'SLACK_BOT_USER_DEFAULT_CHANNEL'
            ),
        ],
    ],

];