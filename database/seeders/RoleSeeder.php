<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * 역할 기본 데이터 생성
     *
     * 시스템을 처음 설치하거나 데이터베이스를 초기화했을 때
     * 기본적으로 사용할 직원 역할을 생성한다.
     */
    public function run(): void
    {
        $roles = [

            /**
             * 일반 직원
             *
             * 별도의 관리 권한이 없는 일반 직원을 의미한다.
             * 실제 사용 가능한 기능은 연결된 permissions를 기준으로 판단한다.
             */
            [
                'code' => 'staff',
                'name' => '일반 직원',
                'description' => '일반적인 업무를 수행하는 직원',
                'is_active' => true,
            ],

            /**
             * 주방 헤드 셰프
             *
             * 주방 업무를 관리하는 책임자 역할이다.
             *
             * 향후 권한 설정을 통해 같은 점포 및 주방 부서 직원의
             * 생산·폐기 기록을 대리 입력하거나 수정할 수 있도록 한다.
             */
            [
                'code' => 'kitchen_head',
                'name' => '주방 헤드 셰프',
                'description' => '주방 업무 및 주방 직원의 생산·폐기 기록을 관리하는 책임자',
                'is_active' => true,
            ],

            /**
             * 홀 매니저
             *
             * 홀 업무를 관리하는 책임자 역할이다.
             *
             * 향후 권한 설정을 통해 같은 점포 및 홀 부서 직원의
             * 관련 업무 기록을 관리할 수 있도록 한다.
             */
            [
                'code' => 'hall_manager',
                'name' => '홀 매니저',
                'description' => '홀 업무 및 홀 직원을 관리하는 책임자',
                'is_active' => true,
            ],

            /**
             * 운영진 일반 직원
             *
             * 점포 운영과 관련된 업무를 담당하는 직원이다.
             *
             * 운영진이라는 이유만으로 생산·폐기 원본 데이터를
             * 수정할 수 있도록 하지 않고 실제 권한은 permissions로 관리한다.
             */
            [
                'code' => 'operations_staff',
                'name' => '운영진',
                'description' => '점포 운영 관련 업무를 담당하는 직원',
                'is_active' => true,
            ],

            /**
             * 운영진 매니저
             *
             * 운영 업무를 관리하는 책임자 역할이다.
             *
             * 구체적인 관리 범위와 기능은 역할명 자체가 아니라
             * 해당 역할에 연결된 permissions를 기준으로 결정한다.
             */
            [
                'code' => 'operations_manager',
                'name' => '운영 매니저',
                'description' => '점포 운영 업무를 관리하는 책임자',
                'is_active' => true,
            ],
        ];

        /**
         * 역할 데이터 저장
         *
         * code를 고유한 조회 기준으로 사용하여 같은 Seeder를
         * 여러 번 실행해도 동일한 역할이 중복 생성되지 않도록 한다.
         *
         * 역할명, 설명 또는 사용 상태가 변경된 경우에는
         * 기존 역할 데이터를 최신 Seeder 내용으로 갱신한다.
         */
        foreach ($roles as $role) {
            Role::updateOrCreate(
                [
                    'code' => $role['code'],
                ],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_active' => $role['is_active'],
                ]
            );
        }
    }
}