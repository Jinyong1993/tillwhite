<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * 역할별 권한을 설정한다.
     *
     * Permission은 "무엇을 할 수 있는가"만 정의한다.
     * 자기 점포, 자기 부서, 본사, 전 점포 같은 데이터 접근 범위는
     * RolePermissionSeeder에서 처리하지 않고 Laravel의 Policy,
     * Service 등의 서버 로직에서 별도로 제한한다.
     */
    public function run(): void
    {
        /**
         * 일반 직원
         *
         * 자기 점포 범위의 기본 업무를 수행한다.
         * 생산·폐기·로스 기록은 등록/수정/삭제가 가능하지만,
         * 실제로 어떤 기록까지 수정/삭제할 수 있는지는
         * Laravel의 데이터 접근 범위에서 제한한다.
         */
        $this->syncPermissions('staff', [
            'production.view',
            'production.create',
            'production.update',
            'production.delete',

            'schedule.view',

            'attendance.view',

            'leave.view',

            'product.view',

            'recipe.view',

            'sales.view',

            'employee.view',

            'position.view',

            'audit.view',
        ]);

        /**
         * 주방 헤드 셰프
         *
         * 자기 점포의 주방 부서를 관리한다.
         * 스케줄, 근태, 휴가, 제품, 레시피 등을 관리할 수 있다.
         *
         * 직원의 인사정보 자체를 수정하는 employee.manage 권한은
         * 부여하지 않는다.
         */
        $this->syncPermissions('kitchen_head', [
            'production.view',
            'production.create',
            'production.update',
            'production.delete',

            'schedule.view',
            'schedule.manage',

            'attendance.view',
            'attendance.manage',

            'leave.view',
            'leave.manage',

            'product.view',
            'product.manage',

            'recipe.view',
            'recipe.manage',

            'sales.view',
            'sales.manage',

            'employee.view',

            'position.view',

            'audit.view',
        ]);

        /**
         * 홀 매니저
         *
         * 자기 점포의 홀 부서를 관리한다.
         * 주방 헤드 셰프와 동일한 관리 권한을 가지지만,
         * 실제 접근 데이터는 홀 부서 범위로 제한한다.
         *
         * 직원의 인사정보 자체를 수정하는 employee.manage 권한은
         * 부여하지 않는다.
         */
        $this->syncPermissions('hall_manager', [
            'production.view',
            'production.create',
            'production.update',
            'production.delete',

            'schedule.view',
            'schedule.manage',

            'attendance.view',
            'attendance.manage',

            'leave.view',
            'leave.manage',

            'product.view',
            'product.manage',

            'recipe.view',
            'recipe.manage',

            'sales.view',
            'sales.manage',

            'employee.view',

            'position.view',

            'audit.view',
        ]);

        /**
         * 본사 직원
         *
         * 전 점포의 생산, 제품, 레시피, 매출, 직원, 점포 정보를
         * 조회할 수 있지만 해당 데이터를 직접 관리하지는 않는다.
         *
         * 근무 스케줄, 근태, 휴가는 본사 직원 범위에서만
         * 조회할 수 있도록 Laravel에서 제한한다.
         */
        $this->syncPermissions('head_office_staff', [
            'production.view',

            'schedule.view',

            'attendance.view',

            'leave.view',

            'product.view',

            'recipe.view',

            'sales.view',

            'employee.view',

            'store.view',

            'position.view',

            'system.view',

            'audit.view',
        ]);

        /**
         * 본사 관리자
         *
         * 전 점포의 주요 업무 데이터를 조회할 수 있다.
         * 직원 인사정보, 점포, 직급, 시스템 설정은 관리할 수 있다.
         *
         * 생산, 제품, 레시피, 매출은 전 점포 조회만 가능하며
         * 직접 수정할 수 있는 관리 권한은 부여하지 않는다.
         *
         * 스케줄, 근태, 휴가 관리 권한은 본사 직원 범위에서만
         * 사용할 수 있도록 Laravel에서 제한한다.
         */
        $this->syncPermissions('head_office_manager', [
            'production.view',

            'schedule.view',
            'schedule.manage',

            'attendance.view',
            'attendance.manage',

            'leave.view',
            'leave.manage',

            'product.view',

            'recipe.view',

            'sales.view',

            'employee.view',
            'employee.manage',

            'store.view',
            'store.manage',

            'position.view',
            'position.manage',

            'system.view',
            'system.manage',

            'audit.view',
        ]);

        /**
         * 최고 관리자
         *
         * 현재 등록되어 있는 모든 Permission을 가진다.
         * 새로운 Permission이 추가되더라도 Seeder를 다시 실행하면
         * 자동으로 최고 관리자 역할에 포함된다.
         *
         * 데이터 접근 범위 역시 전 점포와 본사 전체를 대상으로 한다.
         * 단, audit_logs는 수정/삭제 기능 자체를 제공하지 않고
         * audit.view를 통한 조회만 허용한다.
         */
        $superAdmin = Role::where('code', 'super_admin')->firstOrFail();

        $superAdmin->permissions()->sync(
            Permission::query()->pluck('id')->all()
        );
    }

    /**
     * 역할 코드와 Permission 코드 목록을 이용해 권한을 동기화한다.
     *
     * sync()를 사용하기 때문에 Seeder를 다시 실행했을 때
     * 기존에 잘못 연결되어 있거나 더 이상 필요하지 않은 권한은 제거되고,
     * 현재 코드에 정의된 권한 구성과 동일하게 맞춰진다.
     */
    private function syncPermissions(string $roleCode, array $permissionCodes): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        $permissionIds = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }
}