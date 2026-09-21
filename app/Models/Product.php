<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /**
     * 대량 할당 가능한 제품 속성
     *
     * 제품은 점포별로 관리하며 카테고리, 생산 부서,
     * 관리 부서와 표시 순서를 함께 저장한다.
     */
    protected $fillable = [
        'store_id',
        'product_category_id',
        'name',
        'production_department',
        'management_department',
        'sort_order',
        'is_active',
    ];

    /**
     * 제품 속성 타입 변환
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** 제품이 소속된 점포 */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** 제품 카테고리 */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** 생산·폐기·로스 기록 */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /** 제품 레시피 */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /** 제품 판매가 이력 */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
