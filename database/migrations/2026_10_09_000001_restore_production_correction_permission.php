<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 기존 운영 DB에서 누락된 마감 후 수정 권한을 복구합니다.
     * 본사 조회 전용 역할에는 권한을 추가하지 않습니다.
     */
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'code' => 'production.correct',
            'name' => '생산·폐기 마감 후 수정',
            'description' => '마감된 생산·폐기 기록을 사유와 이력을 남기고 수정할 수 있는 권한',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('permissions')->updateOrInsert(
            ['code' => 'production.correct'],
            [
                'name' => '생산·폐기 마감 후 수정',
                'description' => '마감된 생산·폐기 기록을 사유와 이력을 남기고 수정할 수 있는 권한',
                'is_active' => true,
                'updated_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')->where('code', 'production.correct')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('code', ['staff', 'kitchen_head', 'hall_manager'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    // 권한이 과거부터 존재했을 수 있으므로 운영 데이터 보존을 위해 롤백 시 삭제하지 않습니다.
    public function down(): void
    {
    }
};
