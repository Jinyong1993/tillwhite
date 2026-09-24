<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    /**
     * 프로모션 정보
     *
     * 특정 점포에서 일정 기간 동안 진행되는
     * 할인 및 행사 정보를 관리합니다.
     *
     * 프로모션에 적용되는 제품은
     * promotion_products 테이블을 통해 별도로 관리합니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 프로모션 컬럼의 타입 변환 설정
     *
     * start_date : 프로모션 시작일을 date로 변환
     * end_date   : 프로모션 종료일을 date로 변환
     * is_active  : 프로모션 사용 여부를 boolean으로 변환
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 프로모션이 진행되는 점포
     *
     * promotions.store_id를 기준으로
     * Promotion과 Store를 연결합니다.
     *
     * 하나의 점포에서는 여러 프로모션을
     * 진행할 수 있습니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 프로모션에 포함된 제품 목록
     *
     * promotion_products.promotion_id를 기준으로
     * Promotion과 PromotionProduct를 연결합니다.
     *
     * 하나의 프로모션에는 여러 제품을
     * 포함할 수 있습니다.
     *
     * PromotionProduct에는 프로모션 대상 제품과
     * 해당 제품에 적용되는 할인 정보 등이 저장됩니다.
     *
     * 예:
     * $promotion->products
     *
     * 위와 같이 사용하면 해당 프로모션에 등록된
     * PromotionProduct 목록을 조회할 수 있습니다.
     */
    public function products(): HasMany
    {
        return $this->hasMany(PromotionProduct::class);
    }
}