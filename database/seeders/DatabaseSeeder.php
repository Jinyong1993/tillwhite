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
             * 직원 관리 화면 검증용 테스트 직원 100명
             *
             * 검색/필터/페이징/삭제 및 복구 UI를 검증하기 위한 데이터입니다.
             * 테스트 계정 비밀번호는 모두 test1234입니다.
             */
            EmployeeTestSeeder::class,
            /**
             * 데모 업무 데이터
             *
             * 제품, 생산 기록, 근무 코드 등 화면 확인용 데이터를 생성한다.
             */
            DemoDataSeeder::class,
            /**
             * 제품 관리 화면 검증용 제품 30종
             *
             * 실제 매장에서 납득 가능한 카테고리/담당 부서 조합과
             * 상시/기간한정/취급중단/삭제 상태를 함께 검증합니다.
             */
            ProductSeeder::class,
            // 생산·폐기 관리 최근 10일 통합 검증 데이터
            ProductionManagementSeeder::class,
        ]);
    }
}
