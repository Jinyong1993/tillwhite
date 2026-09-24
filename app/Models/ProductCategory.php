<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    /**
     * 대량 할당 가능한 제품 카테고리 속성
     *
     * store_id   : 카테고리가 등록된 점포
     * name       : 카테고리명
     * sort_order : 카테고리 표시 순서
     * is_active  : 카테고리 사용 여부
     *
     * 제품 카테고리는 점포별로 관리합니다.
     *
     * 같은 이름의 카테고리라도 점포가 다르면
     * 각각 별도의 카테고리로 관리할 수 있습니다.
     *
     * 예:
     * 무역점       → 소금빵
     * 더현대서울점  → 소금빵
     */
    protected $fillable = [
        'store_id',
        'name',
        'sort_order',
        'is_active',
    ];

    /**
     * 제품 카테고리 컬럼의 타입 변환 설정
     *
     * sort_order : 카테고리 표시 순서를 integer로 변환
     * is_active  : 카테고리 사용 여부를 boolean으로 변환
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 카테고리가 소속된 점포
     *
     * product_categories.store_id를 기준으로
     * ProductCategory와 Store를 연결합니다.
     *
     * 하나의 점포에는 여러 제품 카테고리가 존재할 수 있으며,
     * 각 카테고리는 하나의 점포에 소속됩니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 해당 카테고리에 속한 제품 목록
     *
     * products.product_category_id를 기준으로
     * ProductCategory와 Product를 연결합니다.
     *
     * 하나의 카테고리에는 여러 제품이
     * 포함될 수 있습니다.
     *
     * 예:
     * $category->products
     *
     * 위와 같이 사용하면 해당 카테고리에 속한
     * 제품 목록을 조회할 수 있습니다.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}