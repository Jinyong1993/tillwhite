<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * 시스템 기본 권한 데이터 생성
     *
     * 권한 코드는 "기능.행동" 형식으로 통일한다.
     *
     * permission은 사용자가 어떤 기능을 수행할 수 있는지를 판단하며,
     * 어느 점포와 부서의 데이터까지 접근할 수 있는지는
     * Laravel의 Policy 및 Service에서 별도로 검증한다.
     */
    public function run(): void
    {
        $permissions = [

            /**
             * 생산·폐기 관리
             *
             * 생산량, 폐기량, 로스량은 production_records에서
             * 하나의 업무 기록으로 통합하여 관리한다.
             */
            [
                'code' => 'production.view',
                'name' => '생산·폐기 기록 조회',
                'description' => '권한 범위 내 생산·폐기·로스 기록 및 통계를 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'production.create',
                'name' => '생산·폐기 기록 등록',
                'description' => '권한 범위 내 생산·폐기·로스 기록을 등록할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'production.update',
                'name' => '생산·폐기 기록 수정',
                'description' => '권한 범위 내 생산·폐기·로스 기록을 수정할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'production.delete',
                'name' => '생산·폐기 기록 삭제',
                'description' => '권한 범위 내 생산·폐기·로스 기록을 삭제할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 근무 관리
             *
             * 근무 스케줄, 근태, 휴가 및 희망휴무 등의
             * 근무 관련 데이터를 조회하거나 관리한다.
             */
            [
                'code' => 'schedule.view',
                'name' => '근무 스케줄 조회',
                'description' => '권한 범위 내 근무 스케줄을 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'schedule.manage',
                'name' => '근무 스케줄 관리',
                'description' => '권한 범위 내 근무 스케줄을 생성·수정·삭제 및 자동 생성할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'attendance.view',
                'name' => '근태 기록 조회',
                'description' => '권한 범위 내 출퇴근 및 근태 기록을 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'attendance.manage',
                'name' => '근태 기록 관리',
                'description' => '권한 범위 내 근태 기록을 관리할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'leave.view',
                'name' => '휴가 내역 조회',
                'description' => '권한 범위 내 휴가 발생·사용 내역 및 신청 내역을 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'leave.manage',
                'name' => '휴가 관리',
                'description' => '권한 범위 내 휴가 발생·조정 및 휴가 신청을 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 제품 관리
             */
            [
                'code' => 'product.view',
                'name' => '제품 조회',
                'description' => '권한 범위 내 제품 및 제품 카테고리를 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'product.manage',
                'name' => '제품 관리',
                'description' => '권한 범위 내 제품, 제품 카테고리 및 제품 가격을 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 레시피 관리
             *
             * 레시피는 제품 관리 메뉴에 포함되지만
             * 제품 정보와 별도의 권한으로 제어할 수 있도록 분리한다.
             */
            [
                'code' => 'recipe.view',
                'name' => '레시피 조회',
                'description' => '권한 범위 내 레시피를 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'recipe.manage',
                'name' => '레시피 관리',
                'description' => '권한 범위 내 레시피, 재료, 제조 단계 및 이미지를 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 매출 관리
             */
            [
                'code' => 'sales.view',
                'name' => '매출 조회',
                'description' => '권한 범위 내 매출 및 환불 내역과 통계를 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'sales.manage',
                'name' => '매출 관리',
                'description' => '권한 범위 내 매출, 프로모션 및 환불을 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 직원 관리
             */
            [
                'code' => 'employee.view',
                'name' => '직원 조회',
                'description' => '권한 범위 내 직원 정보와 인사발령 이력을 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'employee.manage',
                'name' => '직원 관리',
                'description' => '권한 범위 내 직원 정보 및 인사발령을 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 점포 관리
             */
            [
                'code' => 'store.view',
                'name' => '점포 조회',
                'description' => '점포 정보를 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'store.manage',
                'name' => '점포 관리',
                'description' => '점포 등록, 수정, 운영 상태 및 위치 정보를 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 직급 관리
             *
             * 직급은 직원에게 표시되는 회사 내 직급 및 직책 정보이며,
             * 시스템 권한을 의미하는 role과 분리하여 관리한다.
             */
            [
                'code' => 'position.view',
                'name' => '직급 조회',
                'description' => '직급 정보를 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'position.manage',
                'name' => '직급 관리',
                'description' => '직급 정보를 등록·수정 및 비활성화할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 시스템 관리
             *
             * 역할, 권한, 시스템 설정 등의
             * 전역 관리 기능에 사용하는 권한이다.
             */
            [
                'code' => 'system.view',
                'name' => '시스템 설정 조회',
                'description' => '역할, 권한 및 시스템 설정을 조회할 수 있는 권한',
                'is_active' => true,
            ],
            [
                'code' => 'system.manage',
                'name' => '시스템 설정 관리',
                'description' => '역할, 권한 및 시스템 설정을 관리할 수 있는 권한',
                'is_active' => true,
            ],

            /**
             * 감사 로그 조회
             *
             * 감사 로그는 변경 이력을 보존하는 데이터이므로
             * 조회 권한만 제공한다.
             *
             * 일반적인 수정 또는 삭제 권한은 만들지 않는다.
             */
            [
                'code' => 'audit.view',
                'name' => '감사 로그 조회',
                'description' => '시스템 감사 로그를 조회할 수 있는 권한',
                'is_active' => true,
            ],
        ];

        /**
         * 권한 데이터 저장
         *
         * code를 고유한 기준으로 사용한다.
         * Seeder를 반복 실행해도 권한이 중복 생성되지 않으며,
         * 이름, 설명 또는 사용 상태가 변경되면 기존 데이터를 갱신한다.
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