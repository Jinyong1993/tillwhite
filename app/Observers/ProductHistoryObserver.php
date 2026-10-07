<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class ProductHistoryObserver
{
    /**
     * 분석에 영향을 주는 제품명·카테고리·활성 상태가 바뀔 때
     * 이전 유효 구간을 닫고 새 스냅샷을 기록합니다.
     */
    public function saved(Product $product): void
    {
        if (!Schema::hasTable('product_master_histories')) {
            return;
        }
        if (!$product->wasRecentlyCreated && !$product->wasChanged(['name', 'product_category_id', 'is_active'])) {
            return;
        }
        $this->record($product);
    }

    // Soft Delete 시점도 제품 상태 이력으로 남깁니다.
    public function deleted(Product $product): void
    {
        if (Schema::hasTable('product_master_histories')) {
            $this->record($product);
        }
    }

    // 복구 시점도 같은 제품의 새 유효 구간으로 남깁니다.
    public function restored(Product $product): void
    {
        if (Schema::hasTable('product_master_histories')) {
            $this->record($product);
        }
    }

    // 현재 제품 상태를 새 유효 구간으로 기록합니다.
    private function record(Product $product): void
    {
        $now = now();
        DB::table('product_master_histories')->where('product_id', $product->id)->whereNull('effective_to')->update(['effective_to' => $now->copy()->subSecond(), 'updated_at' => $now]);
        DB::table('product_master_histories')->insert(['product_id' => $product->id, 'name' => $product->name, 'product_category_id' => $product->product_category_id, 'is_active' => $product->is_active, 'is_deleted' => $product->trashed(), 'effective_from' => $now, 'effective_to' => null, 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
    }
}
