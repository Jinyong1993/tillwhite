<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품의 판매 유형/기간과 Soft Delete 컬럼을 추가합니다.
     *
     * 기존 제품 데이터는 모두 상시 제품(regular)으로 유지되므로
     * 이 migration을 적용해도 현재 기능과 데이터가 깨지지 않습니다.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sales_type')
                ->default('regular')
                ->after('management_department');

            $table->date('sales_start_date')
                ->nullable()
                ->after('sales_type');

            $table->date('sales_end_date')
                ->nullable()
                ->after('sales_start_date');

            $table->softDeletes();
        });
    }

    /**
     * 추가한 컬럼을 제거하여 이전 스키마로 되돌립니다.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'sales_type',
                'sales_start_date',
                'sales_end_date',
            ]);
        });
    }
};
