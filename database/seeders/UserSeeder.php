<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * 기본 사용자 데이터 생성
     *
     * 점포별:
     * - 주방 헤드 셰프 1명
     * - 홀 매니저 1명
     * - 일반 직원 3명
     *
     * 본사:
     * - 최고 관리자 1명
     * - 본사 관리자 1명
     * - 본사 직원 1명
     *
     * 기존 admin 계정은 일반 직원 테스트 계정으로 유지한다.
     */
    public function run(): void
    {
        /*
         * 역할 조회
         */
        $staffRole = Role::where('code', 'staff')->firstOrFail();
        $kitchenHeadRole = Role::where('code', 'kitchen_head')->firstOrFail();
        $hallManagerRole = Role::where('code', 'hall_manager')->firstOrFail();

        $headOfficeStaffRole = Role::where('code', 'head_office_staff')->firstOrFail();
        $headOfficeManagerRole = Role::where('code', 'head_office_manager')->firstOrFail();
        $superAdminRole = Role::where('code', 'super_admin')->firstOrFail();

        /*
         * 직급 조회
         */
        $staffPosition = Position::where('code', 'staff')->firstOrFail();
        $managerPosition = Position::where('code', 'manager')->firstOrFail();
        $generalManagerPosition = Position::where('code', 'general_manager')->firstOrFail();
        $presidentPosition = Position::where('code', 'president')->firstOrFail();

        /*
         * 모든 점포 조회
         */
        $stores = Store::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        /*
         * 점포별 직원 생성
         */
        foreach ($stores as $store) {
            /*
             * 주방 헤드 셰프
             */
            $this->createUser(
                employeeCode: 'store_' . $store->store_code . '_kitchen_head',
                name: $store->name . ' 헤드셰프',
                password: '1234',
                storeId: $store->id,
                department: 'kitchen',
                positionId: $managerPosition->id,
                roleId: $kitchenHeadRole->id,
            );

            /*
             * 홀 매니저
             */
            $this->createUser(
                employeeCode: 'store_' . $store->store_code . '_hall_manager',
                name: $store->name . ' 홀매니저',
                password: '1234',
                storeId: $store->id,
                department: 'hall',
                positionId: $managerPosition->id,
                roleId: $hallManagerRole->id,
            );

            /*
             * 일반 직원 3명
             */
            for ($i = 1; $i <= 3; $i++) {
                $this->createUser(
                    employeeCode: 'store_' . $store->store_code . '_staff_' . $i,
                    name: $store->name . ' 직원' . $i,
                    password: '1234',
                    storeId: $store->id,
                    department: $i === 1 ? 'kitchen' : 'hall',
                    positionId: $staffPosition->id,
                    roleId: $staffRole->id,
                );
            }
        }

        /*
         * 본사 최고 관리자
         *
         * 본사 직원이므로 store_id는 NULL이다.
         */
        $this->createUser(
            employeeCode: 'superadmin',
            name: '최고 관리자',
            password: '1234',
            storeId: null,
            department: 'head_office',
            positionId: $presidentPosition->id,
            roleId: $superAdminRole->id,
        );

        /*
         * 본사 관리자
         */
        $this->createUser(
            employeeCode: 'head_admin',
            name: '본사 관리자',
            password: '1234',
            storeId: null,
            department: 'head_office',
            positionId: $generalManagerPosition->id,
            roleId: $headOfficeManagerRole->id,
        );

        /*
         * 본사 직원
         */
        $this->createUser(
            employeeCode: 'head_staff',
            name: '본사 직원',
            password: '1234',
            storeId: null,
            department: 'head_office',
            positionId: $staffPosition->id,
            roleId: $headOfficeStaffRole->id,
        );

        /*
         * 기존 기본 테스트 계정
         *
         * 로그인 기능 테스트용으로 유지한다.
         *
         * admin / 1234
         */
        $this->createUser(
            employeeCode: 'admin',
            name: '홍길동',
            password: '1234',
            storeId: $stores->first()?->id,
            department: 'kitchen',
            positionId: $staffPosition->id,
            roleId: $staffRole->id,
        );
    }

    /**
     * 사용자 생성 또는 갱신
     *
     * employee_code를 기준으로 중복 생성을 방지한다.
     */
    private function createUser(
        string $employeeCode,
        string $name,
        string $password,
        ?int $storeId,
        string $department,
        int $positionId,
        int $roleId,
    ): User {
        return User::updateOrCreate(
            [
                'employee_code' => $employeeCode,
            ],
            [
                'name' => $name,

                /*
                 * User 모델의 'hashed' cast가
                 * 자동으로 비밀번호를 해시한다.
                 */
                'password' => $password,

                'password_changed_at' => null,
                'phone' => null,
                'birth_date' => null,

                'store_id' => $storeId,
                'department' => $department,

                'position_id' => $positionId,
                'role_id' => $roleId,

                'employment_status' => 'active',

                'hired_at' => null,
                'resigned_at' => null,

                'is_active' => true,
                'last_login_at' => null,
            ]
        );
    }
}