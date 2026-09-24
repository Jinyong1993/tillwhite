<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    /**
     * 제품 판매가 이력
     *
     * 제품의 가격을 단순히 products 테이블에 저장하지 않고
     * 적용 기간별로 별도의 가격 이력을 관리합니다.
     *
     * 이를 통해 제품 가격이 변경되더라도
     * 이전 가격 정보를 유지할 수 있습니다.
     *
     * 예:
     * 2026-09-01 ~ 2026-09-30 → 3,500원
     * 2026-10-01 ~ NULL       → 3,800원
     *
     * effective_to가 NULL이면 현재 종료일이 정해지지 않은
     * 가격 정보로 사용할 수 있습니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 가격 적용 기간의 유효성 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 제품 가격 컬럼의 타입 변환 설정
     *
     * price          : 판매 가격을 integer로 변환
     * effective_from : 가격 적용 시작일을 date로 변환
     * effective_to   : 가격 적용 종료일을 date로 변환
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * 가격이 적용되는 제품
     *
     * product_prices.product_id를 기준으로
     * ProductPrice와 Product를 연결합니다.
     *
     * 하나의 제품에는 여러 가격 이력이 존재할 수 있으며,
     * 각각의 ProductPrice는 하나의 제품에 속합니다.
     *
     * 예:
     * $productPrice->product
     *
     * 위와 같이 사용하면 해당 가격이 적용되는
     * 제품 정보를 조회할 수 있습니다.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}