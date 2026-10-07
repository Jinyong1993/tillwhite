<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 재료명만 필수로 입력할 수 있도록 수량과 단위를 선택 항목으로 변경합니다.
    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->nullable()->change();
            $table->string('unit')->nullable()->change();
        });
    }

    // 기존 스키마로 되돌릴 때는 NULL 데이터가 없어야 합니다.
    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->nullable(false)->change();
            $table->string('unit')->nullable(false)->change();
        });
    }
};
