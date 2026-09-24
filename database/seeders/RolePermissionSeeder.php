<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * 역할(Role)별 권한(Permission)을 설정한다.
     *
     * 권한(Permission)은
     * "사용자가 어떤 기능을 사용할 수 있는가"를 정의한다.
     *
     * 예:
     * - production.view = 생산·폐기 조회 권한
     * - product.view = 제품 조회 권한
     * - employee.view = 직원 조회 권한
     * - employee.manage = 직원 관리 권한
     *
     * 자기 점포, 자기 부서, 본사, 전 점포와 같은
     * 실제 데이터 조회 범위(Scope)는 여기에서 결정하지 않는다.
     *
     * 데이터 조회 범위는 Laravel 서버의
     * 데이터 조회 범위(Scope), 서비스(Service) 등의
     * 서버 로직에서 별도로 제한한다.
     */
    public function run(): void
    {
        /**
         * 일반 직원(staff)
         *
         * 자기 점포 범위의 기본 업무를 수행한다.
         *
         * 생산·폐기·로스 기록은
         * 조회, 등록, 수정, 삭제 권한을 가진다.
         *
         * 단, 실제 생산 기록을 등록·수정·삭제할 수 있는지는
         * 점포와 해당 날짜의 근무 여부 등을
         * Laravel 서버에서 추가로 검사한다.
         *
         * 직원 개인정보 보호를 위해
         * 직원 조회 권한(employee.view)은 부여하지 않는다.
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

            'position.view',

            'audit.view',
        ]);

        /**
         * 주방 헤드 셰프(kitchen_head)
         *
         * 자기 점포의 주방 업무를 관리한다.
         *
         * 생산·폐기·로스와 함께
         * 근무 일정, 근태, 휴가, 제품, 레시피,
         * 매출 등의 관리 권한을 가진다.
         *
         * 실제 관리할 수 있는 데이터 범위는
         * Laravel 서버에서 점포 및 업무 규칙에 따라 제한한다.
         *
         * 직원 개인정보 보호를 위해
         * 직원 조회 권한(employee.view)과
         * 직원 관리 권한(employee.manage)은 부여하지 않는다.
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

            'position.view',

            'audit.view',
        ]);

        /**
         * 홀 매니저(hall_manager)
         *
         * 자기 점포의 홀 업무를 관리한다.
         *
         * 생산·폐기·로스와 함께
         * 근무 일정, 근태, 휴가, 제품, 레시피,
         * 매출 등의 관리 권한을 가진다.
         *
         * 실제 관리할 수 있는 데이터 범위는
         * Laravel 서버에서 점포 및 업무 규칙에 따라 제한한다.
         *
         * 직원 개인정보 보호를 위해
         * 직원 조회 권한(employee.view)과
         * 직원 관리 권한(employee.manage)은 부여하지 않는다.
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

            'position.view',

            'audit.view',
        ]);

        /**
         * 본사 직원(head_office_staff)
         *
         * 전 점포의 주요 업무 데이터를 조회할 수 있다.
         *
         * 생산, 제품, 레시피, 매출, 직원, 점포 등의
         * 조회 권한을 가지지만 직접 관리하지는 않는다.
         *
         * 직원 관리에서는
         * 직원 조회 권한(employee.view)만 가지며
         * 직원 관리 권한(employee.manage)은 가지지 않는다.
         *
         * 따라서 직원 정보 조회는 가능하지만
         * 직원 등록, 수정, 상태 변경 등의 관리 작업은 할 수 없다.
         *
         * 실제 조회 가능한 데이터 범위는
         * Laravel 서버에서 별도로 제한한다.
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
         * 본사 관리자(head_office_manager)
         *
         * 전 점포의 주요 업무 데이터를 조회할 수 있다.
         *
         * 직원 관리에서는
         * 직원 조회 권한(employee.view)과
         * 직원 관리 권한(employee.manage)을 모두 가진다.
         *
         * 따라서 직원 정보 조회뿐만 아니라
         * 직원 등록, 수정, 상태 변경 등의 관리 작업도 할 수 있다.
         *
         * 점포, 직급, 시스템 설정 역시
         * 조회 및 관리 권한을 가진다.
         *
         * 생산, 제품, 레시피, 매출은
         * 전 점포 조회만 가능하며
         * 직접 관리할 수 있는 권한은 부여하지 않는다.
         *
         * 근무 일정, 근태, 휴가 관리 권한의 실제 데이터 범위는
         * Laravel 서버에서 본사 업무 범위에 맞게 제한한다.
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
         * 최고 관리자(super_admin)
         *
         * 현재 등록되어 있는 모든 권한(Permission)을 가진다.
         *
         * 새로운 권한(Permission)이 추가되더라도
         * Seeder를 다시 실행하면 자동으로 최고 관리자 역할에 포함된다.
         *
         * 최고 관리자는 전 점포와 본사를 포함한
         * 전체 시스템 데이터를 대상으로 작업할 수 있다.
         *
         * 단, 감사 로그(audit_logs)는
         * 수정·삭제 기능 자체를 제공하지 않으며
         * 감사 로그 조회 권한(audit.view)을 통한 조회만 허용한다.
         */
        $superAdmin = Role::where('code', 'super_admin')->firstOrFail();

        $superAdmin->permissions()->sync(
            Permission::query()->pluck('id')->all()
        );
    }

    /**
     * 역할(Role) 코드와 권한(Permission) 코드 목록을 이용하여
     * 해당 역할의 권한을 동기화한다.
     *
     * 권한 동기화(sync)를 사용하기 때문에
     * Seeder를 다시 실행하면 기존 역할 권한을
     * 현재 코드에 정의된 권한 목록과 동일하게 맞춘다.
     *
     * 따라서 기존에 잘못 연결되어 있거나
     * 더 이상 사용하지 않는 권한은 제거되고,
     * 새롭게 추가한 권한은 연결된다.
     */
    private function syncPermissions(
        string $roleCode,
        array $permissionCodes
    ): void {
        $role = Role::where('code', $roleCode)->firstOrFail();

        $permissionIds = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }
}