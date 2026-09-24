<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    /**
     * 매출 상세 제품 정보
     *
     * 하나의 Sale에 포함된 개별 판매 제품의
     * 정보를 저장합니다.
     *
     * 하나의 매출에 여러 제품이 포함될 수 있으므로
     * 각 제품별 판매 정보를 SaleItem으로 분리하여 관리합니다.
     *
     * 구조:
     *
     * Sale
     * ├─ SaleItem → Product A
     * ├─ SaleItem → Product B
     * └─ SaleItem → Product C
     *
     * 이를 통해 하나의 매출에서 어떤 제품이
     * 몇 개 판매되었는지 개별적으로 관리할 수 있습니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 매출 계산은
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 해당 판매 항목이 속한 매출
     *
     * sale_items.sale_id를 기준으로
     * SaleItem과 Sale을 연결합니다.
     *
     * 여러 SaleItem이 하나의 Sale에
     * 속하는 다대일(Many-to-One) 관계입니다.
     *
     * 예:
     * $saleItem->sale
     *
     * 위와 같이 사용하면 해당 판매 항목이
     * 어느 매출에 포함되어 있는지 조회할 수 있습니다.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * 판매된 제품
     *
     * sale_items.product_id를 기준으로
     * SaleItem과 Product를 연결합니다.
     *
     * 각 SaleItem은 하나의 제품을 나타내며,
     * 같은 제품도 서로 다른 매출에 여러 번
     * 포함될 수 있습니다.
     *
     * 예:
     * $saleItem->product
     *
     * 위와 같이 사용하면 해당 판매 항목의
     * 제품 정보를 조회할 수 있습니다.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}