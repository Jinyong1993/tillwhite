<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 애플리케이션 기본 데이터 생성
     *
     * 데이터베이스 초기화 후 시스템 운영에 필요한
     * 기본 데이터를 의존 관계에 맞는 순서로 생성한다.
     */
    public function run(): void
    {
        $this->call([

            /**
             * 점포 기본 데이터
             *
             * 사용자 및 점포 관련 데이터에서 참조하므로
             * 가장 먼저 생성한다.
             */
            StoreSeeder::class,

            /**
             * 역할 기본 데이터
             *
             * 사용자 생성 및 역할-권한 연결에 필요하다.
             */
            RoleSeeder::class,

            /**
             * 권한 기본 데이터
             *
             * 역할과 권한을 연결하기 전에 생성한다.
             */
            PermissionSeeder::class,

            /**
             * 직급 기본 데이터
             *
             * UserSeeder에서 position_id를 참조하므로
             * 반드시 사용자 생성보다 먼저 실행한다.
             */
            PositionSeeder::class,

            /**
             * 역할과 권한 연결
             *
             * roles와 permissions가 모두 생성된 이후
             * 관계 데이터를 연결한다.
             */
            RolePermissionSeeder::class,

            /**
             * 기본 사용자 생성
             *
             * 사용자는 store_id, position_id, role_id를
             * 참조하므로 관련 기본 데이터가 모두 생성된 후 실행한다.
             */
            UserSeeder::class,

            /**
             * 데모 업무 데이터
             *
             * 제품, 생산 기록, 근무 코드 등 화면 확인용 데이터를 생성한다.
             */
            DemoDataSeeder::class,
        ]);
    }
}