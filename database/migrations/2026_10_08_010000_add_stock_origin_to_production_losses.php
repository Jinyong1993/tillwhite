<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 로스도 폐기와 동일하게 실제 처리일과 원 생산일을 함께 보존합니다.
     * 기존 데이터는 당시 업무일을 귀속일로 사용해 과거 집계가 바뀌지 않게 합니다.
     */
    public function up(): void
    {
        Schema::table('production_losses', function (Blueprint $table) {
            $table->foreignId('stock_lot_id')
                ->nullable()
                ->after('production_batch_id')
                ->constrained('product_stock_lots')
                ->nullOnDelete();
            $table->date('attribution_date')->nullable()->after('work_date');
            $table->index(['store_id', 'attribution_date', 'product_id']);
        });

        DB::table('production_losses')
            ->whereNull('attribution_date')
            ->update(['attribution_date' => DB::raw('work_date')]);
    }

    public function down(): void
    {
        Schema::table('production_losses', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'attribution_date', 'product_id']);
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropColumn('attribution_date');
        });
    }
};
