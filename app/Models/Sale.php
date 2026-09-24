<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /**
     * 매출 정보
     *
     * 점포에서 발생한 하나의 매출 건을 저장합니다.
     *
     * Sale에는 실제 판매된 제품 목록인 SaleItem과
     * 해당 매출에서 발생한 환불 내역인 SaleRefund가
     * 연결될 수 있습니다.
     *
     * 구조:
     *
     * Sale
     * ├─ SaleItem
     * └─ SaleRefund
     *
     * 하나의 매출에는 여러 제품이 포함될 수 있으며,
     * 하나의 매출에서 여러 환불 내역이 발생할 수도 있습니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증, 권한 검사 및
     * 점포별 데이터 접근 범위는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 매출 컬럼의 타입 변환 설정
     *
     * sales_date   : 실제 매출 발생일을 date로 변환
     * confirmed_at : 매출 확정 시간을 datetime으로 변환
     *
     * sales_date는 실제 영업일을 의미하며,
     * created_at은 시스템에 데이터가 등록된 시간이므로
     * 서로 다른 목적으로 사용합니다.
     */
    protected function casts(): array
    {
        return [
            'sales_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * 매출이 발생한 점포
     *
     * sales.store_id를 기준으로
     * Sale과 Store를 연결합니다.
     *
     * 하나의 점포에는 여러 매출이 발생할 수 있으며,
     * 각 Sale은 하나의 점포에 속합니다.
     *
     * 예:
     * $sale->store
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 매출에 포함된 판매 제품 목록
     *
     * sale_items.sale_id를 기준으로
     * Sale과 SaleItem을 연결합니다.
     *
     * 하나의 매출에는 여러 제품이 포함될 수 있으므로
     * 일대다(One-to-Many) 관계를 가집니다.
     *
     * 각 SaleItem에는 판매 제품, 판매 수량,
     * 판매 당시의 단가 및 금액 등의 정보가 저장됩니다.
     *
     * 예:
     * $sale->items
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * 매출에서 발생한 환불 내역
     *
     * sale_refunds.sale_id를 기준으로
     * Sale과 SaleRefund를 연결합니다.
     *
     * 하나의 매출에서 여러 번의 환불이
     * 발생할 수 있으므로 일대다(One-to-Many)
     * 관계를 가집니다.
     *
     * 원본 매출 기록을 직접 삭제하거나 변경하지 않고
     * 별도의 SaleRefund를 생성하여 환불 이력을
     * 추적할 수 있도록 구성합니다.
     *
     * 예:
     * $sale->refunds
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class);
    }
}