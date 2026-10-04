<?php

return [
    /*
     * Blade 템플릿을 찾을 기본 경로입니다.
     */
    'paths' => [resource_path('views')],
    /*
     * 컴파일된 Blade 파일을 저장할 경로입니다.
     * VIEW_COMPILED_PATH가 없으면 Laravel 기본 storage 경로를 사용합니다.
     */
    'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views')) ?: storage_path('framework/views')),
];
