<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    /**
     * 생산·폐기 관리 개편에 필요한 업무 테이블을 생성합니다.
     *
     * 기존 production_records는 이전 데이터 호환을 위해 유지하고,
     * 신규 화면은 생산 배치와 재고 흐름을 분리한 아래 테이블을 사용합니다.
     */
    public function up(): void
    {
        Schema::create('production_batch_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('work_date');
            $table->string('name')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'work_date']);
        });
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_group_id')->nullable()->constrained('production_batch_groups')->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('quantity');
            $table->boolean('recipe_deviated')->default(false);
            $table->text('recipe_deviation_note')->nullable();
            $table->json('recipe_snapshot')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'work_date', 'product_id']);
        });
        Schema::create('production_batch_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('process_type')->default('all');
            $table->timestamps();
            $table->unique(['production_batch_id', 'user_id', 'process_type'], 'production_batch_worker_unique');
        });
        Schema::create('production_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->date('work_date');
            $table->boolean('production_confirmed')->default(false);
            $table->string('zero_production_reason')->nullable();
            $table->text('zero_production_note')->nullable();
            $table->boolean('loss_confirmed')->default(false);
            $table->boolean('waste_confirmed')->default(false);
            $table->boolean('disposition_confirmed')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'product_id', 'work_date'], 'production_confirmation_unique');
        });
        Schema::create('production_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('quantity');
            $table->text('note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'work_date', 'product_id']);
        });
        Schema::create('production_loss_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_loss_id')->constrained('production_losses')->cascadeOnDelete();
            $table->string('reason_code');
            $table->string('reason_text')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
        Schema::create('product_stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->date('origin_production_date');
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('remaining_quantity');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['store_id', 'product_id', 'origin_production_date']);
        });
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_lot_id')->constrained('product_stock_lots')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->date('work_date');
            $table->string('movement_type');
            $table->unsignedInteger('quantity');
            $table->string('reason_code')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'work_date', 'product_id']);
            $table->index(['stock_lot_id', 'work_date']);
        });
        Schema::create('production_wastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained('product_stock_lots')->nullOnDelete();
            $table->date('work_date');
            $table->date('attribution_date');
            $table->unsignedInteger('quantity');
            $table->text('note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'work_date', 'product_id']);
            $table->index(['store_id', 'attribution_date', 'product_id']);
        });
        Schema::create('production_waste_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_waste_id')->constrained('production_wastes')->cascadeOnDelete();
            $table->string('reason_code');
            $table->string('reason_text')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
        Schema::create('production_other_outflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained('product_stock_lots')->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('quantity');
            $table->string('reason_code');
            $table->string('reason_text')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'work_date', 'product_id']);
        });
        Schema::create('production_daily_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('work_date');
            $table->string('status')->default('in_progress');
            $table->text('daily_memo')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'work_date']);
        });
        Schema::create('production_corrections', function (Blueprint $table) {
            $table->id();
            $table->uuid('correction_group')->unique();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('work_date');
            $table->text('reason');
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('store_daily_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('work_date');
            $table->string('status')->default('open');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'work_date']);
        });
        Schema::create('store_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedInteger('order_quantity')->nullable();
            $table->text('memo')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'start_date', 'end_date']);
        });
        Schema::create('store_calendar_event_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('store_calendar_events')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'product_id']);
        });
        Schema::create('store_daily_weather', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('work_date');
            $table->decimal('average_temperature', 5, 2)->nullable();
            $table->decimal('minimum_temperature', 5, 2)->nullable();
            $table->decimal('maximum_temperature', 5, 2)->nullable();
            $table->decimal('average_humidity', 5, 2)->nullable();
            $table->decimal('precipitation', 8, 2)->nullable();
            $table->string('condition')->nullable();
            $table->string('collection_status')->default('pending');
            $table->json('initial_snapshot')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'work_date']);
        });
        Schema::create('production_recommendation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->date('target_date');
            $table->unsignedInteger('center_quantity')->nullable();
            $table->unsignedInteger('range_min')->nullable();
            $table->unsignedInteger('range_max')->nullable();
            $table->string('confidence')->default('insufficient');
            $table->string('calculation_version')->default('v1');
            $table->json('reasons')->nullable();
            $table->json('excluded_conditions')->nullable();
            $table->date('data_from')->nullable();
            $table->date('data_to')->nullable();
            $table->boolean('referenced')->default(false);
            $table->string('deviation_reason')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['store_id', 'product_id', 'target_date']);
        });
        Schema::create('product_category_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['product_category_id', 'effective_from', 'effective_to']);
        });
        Schema::create('product_master_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name');
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->boolean('is_active');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'effective_from', 'effective_to']);
        });
    }

    // 생산·폐기 관리 개편 테이블을 의존 관계의 역순으로 제거합니다.
    public function down(): void
    {
        Schema::dropIfExists('product_master_histories');
        Schema::dropIfExists('product_category_histories');
        Schema::dropIfExists('production_recommendation_snapshots');
        Schema::dropIfExists('store_daily_weather');
        Schema::dropIfExists('store_calendar_event_products');
        Schema::dropIfExists('store_calendar_events');
        Schema::dropIfExists('store_daily_statuses');
        Schema::dropIfExists('production_corrections');
        Schema::dropIfExists('production_daily_closures');
        Schema::dropIfExists('production_other_outflows');
        Schema::dropIfExists('production_waste_reasons');
        Schema::dropIfExists('production_wastes');
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_stock_lots');
        Schema::dropIfExists('production_loss_reasons');
        Schema::dropIfExists('production_losses');
        Schema::dropIfExists('production_confirmations');
        Schema::dropIfExists('production_batch_workers');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('production_batch_groups');
    }
};
