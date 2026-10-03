<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 레시피 삭제/복구 상태와 삭제 원인을 안전하게 구분할 컬럼을 추가합니다. */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // Soft Delete 후 같은 이름의 새 레시피를 등록할 수 있도록 기존 물리 삭제 기준 unique를 제거합니다.
            $table->dropUnique('recipes_store_id_product_id_name_unique');

            $table->foreignId('deleted_by')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('deletion_source', 20)->nullable()->after('deleted_by');
            $table->softDeletes();
        });
    }

    /** 추가한 Soft Delete 관련 컬럼을 제거합니다. */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('deletion_source');
            $table->dropSoftDeletes();
            $table->unique(['store_id', 'product_id', 'name']);
        });
    }
};
