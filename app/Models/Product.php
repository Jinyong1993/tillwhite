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
     * store_id              : 제품이 등록된 점포
     * product_category_id   : 제품이 속한 카테고리
     * name                  : 제품명
     * production_department : 제품을 실제로 생산하는 부서
     * management_department : 제품 정보를 관리하는 부서
     * sort_order            : 제품 표시 순서
     * is_active             : 제품 사용 여부
     *
     * 제품은 점포별로 관리합니다.
     *
     * production_department와 management_department는
     * 서로 다른 역할을 가질 수 있습니다.
     *
     * production_department
     * - 실제 제품 생산을 담당하는 부서를 의미합니다.
     *
     * management_department
     * - 제품 정보 및 관련 데이터를 관리할 수 있는
     * 담당 부서를 의미합니다.
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
     * 제품 컬럼의 타입 변환 설정
     *
     * sort_order : 제품 표시 순서를 integer로 변환
     * is_active  : 제품 사용 여부를 boolean으로 변환
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 제품이 소속된 점포
     *
     * products.store_id를 기준으로
     * Product와 Store를 연결합니다.
     *
     * 하나의 점포에는 여러 제품이 존재할 수 있으며,
     * 각 제품은 하나의 점포에 소속됩니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 제품이 속한 카테고리
     *
     * products.product_category_id를 기준으로
     * Product와 ProductCategory를 연결합니다.
     *
     * 하나의 카테고리에는 여러 제품이
     * 포함될 수 있습니다.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ProductCategory::class,
            'product_category_id'
        );
    }

    /**
     * 제품의 생산·폐기·로스 기록
     *
     * 하나의 제품에는 여러 ProductionRecord가
     * 등록될 수 있습니다.
     *
     * 생산량, 폐기량, 로스량 등의 기록을
     * 제품별로 조회할 때 사용합니다.
     */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * 제품의 레시피 목록
     *
     * 하나의 제품에는 여러 Recipe를
     * 등록할 수 있습니다.
     *
     * 각 레시피에는 재료와 작업 순서 등의
     * 상세 정보가 연결될 수 있습니다.
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * 제품의 판매가 이력
     *
     * 하나의 제품은 기간에 따라 여러 판매가를
     * 가질 수 있으므로 ProductPrice와
     * 일대다(One-to-Many) 관계를 가집니다.
     *
     * 기존 가격을 덮어쓰는 대신 가격 이력을 남겨
     * 적용 기간별 판매가를 관리할 수 있습니다.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}