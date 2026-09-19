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
             * 점포 기본 데이터 생성
             *
             * 사용자 데이터가 점포를 참조하므로
             * 가장 먼저 점포 데이터를 생성한다.
             */
            StoreSeeder::class,

            /**
             * 역할 기본 데이터 생성
             *
             * 사용자가 role_id를 참조하고
             * 역할별 권한 연결에도 필요하므로 먼저 생성한다.
             */
            RoleSeeder::class,

            /**
             * 권한 기본 데이터 생성
             *
             * 역할과 권한을 연결하기 전에
             * 시스템에서 사용할 권한 데이터를 생성한다.
             */
            PermissionSeeder::class,

            /**
             * 역할과 권한 연결
             *
             * 앞에서 생성된 roles와 permissions를 이용하여
             * role_permissions 관계 데이터를 생성한다.
             */
            RolePermissionSeeder::class,

            /**
             * 기본 사용자 생성
             *
             * 사용자는 store_id와 role_id를 필요로 하므로
             * 점포와 역할 데이터가 생성된 이후 마지막에 생성한다.
             */
            UserSeeder::class,
        ]);
    }
}