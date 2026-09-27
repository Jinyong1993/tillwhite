<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeTestSeeder extends Seeder
{
    /**
     * 직원 관리 화면 검증용 테스트 직원 100명을 생성합니다.
     *
     * 검색, 상태 필터, 삭제 직원 표시/복구, 5/10/30개 클라이언트 페이징을
     * 한 화면에서 충분히 확인할 수 있도록 점포/부서/역할/재직 상태를 분산합니다.
     * 모든 테스트 직원의 로그인 비밀번호는 test1234이며 User 모델의 hashed cast를
     * 통해 데이터베이스에는 평문이 아닌 해시값으로 저장됩니다.
     */
    public function run(): void
    {
        $stores = Store::where('status', 'active')->orderBy('sort_order')->orderBy('id')->get();
        $staffPosition = Position::where('code', 'staff')->firstOrFail();
        $managerPosition = Position::where('code', 'manager')->firstOrFail();
        $staffRole = Role::where('code', 'staff')->firstOrFail();
        $kitchenHeadRole = Role::where('code', 'kitchen_head')->firstOrFail();
        $hallManagerRole = Role::where('code', 'hall_manager')->firstOrFail();

        if ($stores->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 100; $i++) {
            $store = $stores[($i - 1) % $stores->count()];
            $department = $i % 2 === 0 ? 'hall' : 'kitchen';
            $isManager = $i % 10 === 0;
            $role = $isManager
                ? ($department === 'kitchen' ? $kitchenHeadRole : $hallManagerRole)
                : $staffRole;

            // 1~70 재직, 71~85 휴직, 86~100 퇴사로 상태 필터 테스트 범위를 만듭니다.
            $employmentStatus = $i <= 70 ? 'active' : ($i <= 85 ? 'leave' : 'resigned');
            $employeeCode = (string) (900000 + $i);

            // Soft Delete된 기존 테스트 직원도 Seeder 재실행 시 다시 찾아 안전하게 갱신합니다.
            $user = User::withTrashed()->firstOrNew(['employee_code' => $employeeCode]);
            if ($user->trashed()) {
                $user->restore();
            }

            $user->fill([
                'name' => '테스트 직원 ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'password' => 'test1234',
                'password_changed_at' => null,
                'phone' => '010' . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                'birth_date' => now()->subYears(20 + ($i % 25))->subDays($i)->toDateString(),
                'store_id' => $store->id,
                'department' => $department,
                'position_id' => $isManager ? $managerPosition->id : $staffPosition->id,
                'role_id' => $role->id,
                'employment_status' => $employmentStatus,
                'hired_at' => now()->subDays(100 + $i)->toDateString(),
                'resigned_at' => $employmentStatus === 'resigned' ? now()->subDays($i % 20)->toDateString() : null,
                'is_active' => $employmentStatus === 'active',
                'last_login_at' => null,
            ]);
            $user->save();

            // 마지막 10명은 삭제 직원 UI/검색/복구 검증용 Soft Delete 상태로 둡니다.
            if ($i > 90) {
                $user->delete();
            }
        }
    }
}
