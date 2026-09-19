<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * 권한 기본 데이터 생성
     *
     * 시스템에서 사용하는 기능별 권한을 생성한다.
     *
     * 권한 코드는 "기능.행동" 형식으로 통일하여
     * 기능이 확장되더라도 동일한 규칙으로 권한을 추가할 수 있도록 한다.
     */
    public function run(): void
    {
        $permissions = [

            /**
             * 생산 기록 조회 권한
             *
             * 생산 기록 및 생산 관련 데이터를
             * 조회할 수 있는 권한이다.
             */
            [
                'code' => 'production.view',
                'name' => '생산 기록 조회',
                'description' => '생산 기록을 조회할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 생산 기록 생성 권한
             *
             * 사용자가 자신의 생산 기록을
             * 직접 등록할 수 있는 권한이다.
             */
            [
                'code' => 'production.create',
                'name' => '생산 기록 등록',
                'description' => '자신의 생산 기록을 등록할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 생산 기록 수정 권한
             *
             * 사용자가 자신이 작업한 생산 기록을
             * 수정할 수 있는 권한이다.
             */
            [
                'code' => 'production.update',
                'name' => '생산 기록 수정',
                'description' => '자신의 생산 기록을 수정할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 생산 기록 대리 생성 권한
             *
             * 권한 범위 내 다른 직원의 생산 기록을
             * 대신 등록할 수 있는 권한이다.
             *
             * 실제 대상 직원의 점포, 부서 및 근무 여부는
             * 서버의 업무 로직에서 별도로 검증한다.
             */
            [
                'code' => 'production.proxy_create',
                'name' => '생산 기록 대리 등록',
                'description' => '권한 범위 내 다른 직원의 생산 기록을 대신 등록할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 생산 기록 대리 수정 권한
             *
             * 권한 범위 내 다른 직원의 생산 기록을
             * 대신 수정할 수 있는 권한이다.
             */
            [
                'code' => 'production.proxy_update',
                'name' => '생산 기록 대리 수정',
                'description' => '권한 범위 내 다른 직원의 생산 기록을 대신 수정할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 폐기 기록 조회 권한
             *
             * 폐기 기록 및 폐기 관련 데이터를
             * 조회할 수 있는 권한이다.
             */
            [
                'code' => 'waste.view',
                'name' => '폐기 기록 조회',
                'description' => '폐기 기록을 조회할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 폐기 기록 생성 권한
             *
             * 사용자가 자신의 폐기 기록을
             * 직접 등록할 수 있는 권한이다.
             */
            [
                'code' => 'waste.create',
                'name' => '폐기 기록 등록',
                'description' => '자신의 폐기 기록을 등록할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 폐기 기록 수정 권한
             *
             * 사용자가 자신이 작업한 폐기 기록을
             * 수정할 수 있는 권한이다.
             */
            [
                'code' => 'waste.update',
                'name' => '폐기 기록 수정',
                'description' => '자신의 폐기 기록을 수정할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 폐기 기록 대리 생성 권한
             *
             * 권한 범위 내 다른 직원의 폐기 기록을
             * 대신 등록할 수 있는 권한이다.
             */
            [
                'code' => 'waste.proxy_create',
                'name' => '폐기 기록 대리 등록',
                'description' => '권한 범위 내 다른 직원의 폐기 기록을 대신 등록할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 폐기 기록 대리 수정 권한
             *
             * 권한 범위 내 다른 직원의 폐기 기록을
             * 대신 수정할 수 있는 권한이다.
             */
            [
                'code' => 'waste.proxy_update',
                'name' => '폐기 기록 대리 수정',
                'description' => '권한 범위 내 다른 직원의 폐기 기록을 대신 수정할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 근무 스케줄 조회 권한
             *
             * 직원의 근무 스케줄을
             * 조회할 수 있는 권한이다.
             */
            [
                'code' => 'schedule.view',
                'name' => '근무 스케줄 조회',
                'description' => '근무 스케줄을 조회할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 근무 스케줄 관리 권한
             *
             * 근무 스케줄을 생성하거나 수정하는 등
             * 스케줄을 관리할 수 있는 권한이다.
             */
            [
                'code' => 'schedule.manage',
                'name' => '근무 스케줄 관리',
                'description' => '근무 스케줄을 관리할 수 있는 권한',
                'is_active' => true,
            ],
        ];

        /**
         * 권한 데이터 저장
         *
         * code를 고유한 조회 기준으로 사용하여 같은 Seeder를
         * 여러 번 실행해도 동일한 권한이 중복 생성되지 않도록 한다.
         *
         * 권한명, 설명 또는 사용 상태가 변경된 경우에는
         * 기존 권한 데이터를 최신 Seeder 내용으로 갱신한다.
         */
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'code' => $permission['code'],
                ],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'is_active' => $permission['is_active'],
                ]
            );
        }
    }
}