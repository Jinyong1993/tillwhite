<?php

namespace App\Observers;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class ProductCategoryHistoryObserver
{
    /** 카테고리명·활성 상태 변경 시 과거 표시와 분석에 사용할 유효기간 이력을 남깁니다. */
    public function saved(ProductCategory $category): void
    {
        if (!Schema::hasTable('product_category_histories')) {
            return;
        }
        if (!$category->wasRecentlyCreated && !$category->wasChanged(['name', 'is_active'])) {
            return;
        }
        $now = now();
        DB::table('product_category_histories')->where('product_category_id', $category->id)->whereNull('effective_to')->update(['effective_to' => $now->copy()->subSecond(), 'updated_at' => $now]);
        DB::table('product_category_histories')->insert(['product_category_id' => $category->id, 'name' => $category->name, 'is_active' => $category->is_active, 'effective_from' => $now, 'effective_to' => null, 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
    }
}
