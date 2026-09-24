<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionProduct extends Model
{
    /**
     * 프로모션 적용 제품 정보
     *
     * 하나의 프로모션에 어떤 제품이 포함되는지와
     * 해당 제품에 적용할 할인 정보를 저장합니다.
     *
     * Promotion과 Product 사이에서
     * 프로모션별 제품 정보를 관리하는 역할을 합니다.
     *
     * 예:
     * 프로모션 → 가을 할인 행사
     * 제품     → 소금빵
     * 할인값   → 해당 제품에 적용할 할인 값
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 프로모션 적용 조건은
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 프로모션 제품 컬럼의 타입 변환 설정
     *
     * discount_value를 소수점 2자리 형식으로 변환합니다.
     *
     * 할인율 또는 할인 금액 등 실제 discount_value의 의미는
     * promotion_products 테이블에 저장된 할인 방식과
     * 관련 비즈니스 로직에 따라 결정됩니다.
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
        ];
    }
}