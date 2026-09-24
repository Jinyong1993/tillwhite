<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleRefund extends Model
{
    /**
     * 매출 환불 정보
     *
     * 기존 Sale에서 발생한 환불 내역을 저장합니다.
     *
     * 원본 매출 데이터를 직접 삭제하거나 변경하지 않고
     * 별도의 환불 기록을 생성하여 관리합니다.
     *
     * 이를 통해 최초 판매 내역과 이후 발생한 환불 내역을
     * 각각 보존하고 추적할 수 있습니다.
     *
     * 구조:
     *
     * Sale
     * └─ SaleRefund
     *
     * 하나의 Sale에는 여러 SaleRefund가
     * 연결될 수 있습니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증, 환불 처리 및 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 환불 컬럼의 타입 변환 설정
     *
     * refund_date : 실제 환불이 발생한 날짜를 date로 변환
     *
     * refund_date는 실제 환불 처리일을 의미하며,
     * created_at은 시스템에 환불 기록이 등록된 시간이므로
     * 서로 다른 목적으로 사용합니다.
     */
    protected function casts(): array
    {
        return [
            'refund_date' => 'date',
        ];
    }
}