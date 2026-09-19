<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * 역할별 권한 연결
     *
     * roles와 permissions 사이의 다대다 관계를
     * role_permissions 테이블에 등록한다.
     *
     * 역할은 사용자의 업무 위치를 나타내고,
     * 실제 기능 사용 가능 여부는 연결된 권한을 기준으로 판단한다.
     */
    public function run(): void
    {
        $rolePermissions = [

            /**
             * 일반 직원 권한
             *
             * 자신의 생산 및 폐기 기록을
             * 조회, 등록, 수정할 수 있다.
             *
             * 다른 직원의 기록을 대신 등록하거나
             * 수정할 수 있는 대리 권한은 부여하지 않는다.
             */
            'staff' => [
                'production.view',
                'production.create',
                'production.update',

                'waste.view',
                'waste.create',
                'waste.update',
            ],

            /**
             * 주방 헤드 셰프 권한
             *
             * 일반적인 생산·폐기 업무뿐만 아니라
             * 관리 범위 내 주방 직원의 기록을 대리 처리할 수 있다.
             *
             * 실제 대리 처리 가능 범위는 권한 존재 여부만으로
             * 결정하지 않고 Laravel 업무 로직에서 점포, 부서,
             * 대상 직원의 근무 여부 등을 추가로 검증한다.
             */
            'kitchen_head' => [
                'production.view',
                'production.create',
                'production.update',
                'production.proxy_create',
                'production.proxy_update',

                'waste.view',
                'waste.create',
                'waste.update',
                'waste.proxy_create',
                'waste.proxy_update',

                'schedule.view',
            ],

            /**
             * 홀 매니저 권한
             *
             * 자신의 업무 기록을 처리할 수 있으며
             * 관리 범위 내 홀 직원의 기록을 대리 처리할 수 있다.
             *
             * 실제 대리 처리 시에는 같은 점포 및 홀 부서인지
             * 서버의 업무 로직에서 추가로 검증한다.
             */
            'hall_manager' => [
                'production.view',
                'production.create',
                'production.update',
                'production.proxy_create',
                'production.proxy_update',

                'waste.view',
                'waste.create',
                'waste.update',
                'waste.proxy_create',
                'waste.proxy_update',

                'schedule.view',
            ],

            /**
             * 운영진 일반 직원 권한
             *
             * 생산 및 폐기 현황과 근무 스케줄을
             * 조회할 수 있도록 한다.
             *
             * 운영진이라는 이유만으로 생산 및 폐기 원본 데이터를
             * 직접 생성하거나 수정하는 권한은 부여하지 않는다.
             */
            'operations_staff' => [
                'production.view',
                'waste.view',
                'schedule.view',
            ],

            /**
             * 운영 매니저 권한
             *
             * 생산 및 폐기 현황을 조회할 수 있으며
             * 근무 스케줄을 조회하고 관리할 수 있다.
             *
             * 생산 및 폐기 원본 데이터에 대한 직접 수정 권한은
             * 운영 매니저라는 이유만으로 자동 부여하지 않는다.
             */
            'operations_manager' => [
                'production.view',
                'waste.view',

                'schedule.view',
                'schedule.manage',
            ],
        ];

        /**
         * 역할별 권한 저장
         *
         * 역할 code를 이용하여 Role을 조회하고
         * 권한 code에 해당하는 permission ID를 가져온다.
         *
         * sync()를 사용하여 Seeder에 정의된 권한 구성을
         * role_permissions 테이블과 동일하게 맞춘다.
         *
         * 따라서 Seeder를 여러 번 실행해도
         * 동일한 연결 데이터가 중복 생성되지 않는다.
         */
        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $role = Role::where('code', $roleCode)->firstOrFail();

            $permissionIds = \App\Models\Permission::whereIn(
                'code',
                $permissionCodes
            )->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}