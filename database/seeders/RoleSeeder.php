<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * 시스템 기본 역할 데이터 생성
     *
     * 역할은 직원의 시스템 권한 묶음을 의미한다.
     * 실제로 사용할 수 있는 기능은 역할 이름을 직접 검사하지 않고
     * role_permissions를 통해 연결된 permissions를 기준으로 판단한다.
     *
     * 부서(department), 직급(position), 역할(role)은 서로 다른 개념으로 관리한다.
     */
    public function run(): void
    {
        $roles = [

            /**
             * 일반 직원
             *
             * 주방 또는 홀에서 근무하는 일반 직원에게 사용하는 역할이다.
             * 기본적인 조회 및 본인의 업무 처리 권한을 부여하는 용도로 사용한다.
             */
            [
                'code' => 'staff',
                'name' => '일반 직원',
                'description' => '주방 또는 홀에서 일반적인 업무를 수행하는 직원',
                'is_active' => true,
            ],

            /**
             * 주방 헤드 셰프
             *
             * 자신의 점포 및 주방 부서를 관리하는 책임자 역할이다.
             * 구체적인 관리 기능은 연결된 permissions를 기준으로 판단한다.
             */
            [
                'code' => 'kitchen_head',
                'name' => '주방 헤드 셰프',
                'description' => '점포의 주방 업무 및 주방 직원을 관리하는 책임자',
                'is_active' => true,
            ],

            /**
             * 홀 매니저
             *
             * 자신의 점포 및 홀 부서를 관리하는 책임자 역할이다.
             * 구체적인 관리 기능은 연결된 permissions를 기준으로 판단한다.
             */
            [
                'code' => 'hall_manager',
                'name' => '홀 매니저',
                'description' => '점포의 홀 업무 및 홀 직원을 관리하는 책임자',
                'is_active' => true,
            ],

            /**
             * 본사 직원
             *
             * 특정 점포에 소속되지 않는 일반 본사 직원 역할이다.
             *
             * 본사 직원이라는 이유만으로 모든 데이터를 수정할 수 있는 것은 아니며,
             * 실제 기능 권한과 데이터 접근 범위는 별도로 판단한다.
             */
            [
                'code' => 'head_office_staff',
                'name' => '본사 직원',
                'description' => '본사 업무를 수행하는 일반 직원',
                'is_active' => true,
            ],

            /**
             * 본사 관리자
             *
             * 본사의 관리 업무를 담당하는 역할이다.
             *
             * 일반 본사 직원보다 넓은 관리 권한을 부여할 수 있지만,
             * 실제 권한은 role_permissions를 기준으로 판단한다.
             */
            [
                'code' => 'head_office_manager',
                'name' => '본사 관리자',
                'description' => '본사의 관리 업무를 담당하는 관리자',
                'is_active' => true,
            ],

            /**
             * 최고 관리자
             *
             * 시스템 전체 관리가 필요한 계정에 사용하는 역할이다.
             *
             * users 테이블에 별도의 super_admin boolean 컬럼을 두지 않고
             * 일반 역할과 동일하게 roles 및 permissions 구조를 사용한다.
             *
             * 최고 관리자 역시 필요한 권한을 명시적으로 부여하여
             * 기능별 권한 구조를 일관되게 유지한다.
             */
            [
                'code' => 'super_admin',
                'name' => '최고 관리자',
                'description' => '시스템 전체 관리 권한을 부여할 수 있는 최고 관리자',
                'is_active' => true,
            ],
        ];

        /**
         * 역할 데이터 저장
         *
         * code를 고유한 기준으로 사용한다.
         * Seeder를 여러 번 실행해도 같은 역할이 중복 생성되지 않으며,
         * 이름이나 설명 등이 변경되면 기존 데이터를 갱신한다.
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