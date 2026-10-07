<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 생산 기록을 직접 입력·수정하는 일반 직원도 마감 후 수정 절차를 사용할 수 있게 합니다.
     * 본사 조회 전용 역할에는 권한을 추가하지 않아 기존 읽기 전용 정책을 유지합니다.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'staff')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'production.correct')->value('id');

        if ($roleId && $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    // 롤백 시 이번 마이그레이션이 추가한 일반 직원의 마감 후 수정 권한만 제거합니다.
    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'staff')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'production.correct')->value('id');

        if ($roleId && $permissionId) {
            DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
