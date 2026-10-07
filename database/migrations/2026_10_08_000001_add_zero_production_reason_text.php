<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 직접입력한 생산 0개 사유를 메모와 분리해 보존합니다.
    public function up(): void
    {
        Schema::table('production_confirmations', function (Blueprint $table) {
            $table->string('zero_production_reason_text', 120)
                ->nullable()
                ->after('zero_production_reason');
        });
    }

    // 롤백 시 이번 변경에서 추가한 사유 직접입력 컬럼만 제거합니다.
    public function down(): void
    {
        Schema::table('production_confirmations', function (Blueprint $table) {
            $table->dropColumn('zero_production_reason_text');
        });
    }
};
