<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 판매 환불 상세 테이블 생성
     *
     * 하나의 환불 건에 포함되는
     * 실제 환불 제품, 수량 및 금액을 관리한다.
     *
     * 원본 sale_items와 직접 연결하여
     * 정상판매, 프로모션 판매, 기프트 등
     * 실제 판매 당시의 기록을 기준으로
     * 환불을 처리할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('sale_refund_items', function (Blueprint $table) {

            /**
             * 환불 상세 고유 ID
             */
            $table->id();

            /**
             * 환불 기본정보
             *
             * sale_refunds.id를 참조한다.
             *
             * 하나의 환불 건에 여러 제품이
             * 포함될 수 있다.
             *
             * 환불 기본정보가 실제 삭제되는 경우
             * 해당 상세정보도 함께 삭제한다.
             */
            $table->foreignId('sale_refund_id')
                ->constrained('sale_refunds')
                ->cascadeOnDelete();

            /**
             * 원본 판매 상세
             *
             * sale_items.id를 직접 참조한다.
             *
             * 어떤 판매 기록에서 발생한 환불인지
             * 정확하게 추적하기 위한 값이다.
             *
             * 예:
             *
             * 정상가 6,000원 판매
             * 프로모션가 4,500원 판매
             *
             * 같은 제품이라도 서로 다른 sale_item이므로
             * 실제 구매 당시 판매가격을 기준으로
             * 정확하게 환불할 수 있다.
             */
            $table->foreignId('sale_item_id')
                ->constrained('sale_items')
                ->restrictOnDelete();

            /**
             * 환불 수량
             *
             * 해당 판매 상세에서 실제로
             * 환불된 제품 수량을 저장한다.
             *
             * 반드시 1 이상이어야 하며,
             * 기존 확정 환불까지 합산한 누적 환불수량이
             * 원본 sale_item의 판매수량을 초과하지 않도록
             * Laravel에서 검증한다.
             */
            $table->unsignedInteger('quantity');

            /**
             * 환불 단가
             *
             * 제품 1개당 실제 환불한 금액을 저장한다.
             *
             * 일반적으로 원본 sale_item의
             * actual_unit_price를 기준으로 계산한다.
             *
             * 환불 당시의 금액을 스냅샷으로 보존하여
             * 이후 제품 가격이나 프로모션이 변경되어도
             * 과거 환불 기록에 영향을 주지 않는다.
             */
            $table->unsignedInteger('refund_unit_price');

            /**
             * 실제 환불금액
             *
             * 기본적으로
             *
             * refund_unit_price × quantity
             *
             * 결과를 저장한다.
             *
             * 금액 계산은 Laravel에서 수행하고
             * 클라이언트가 전달한 계산 결과를
             * 그대로 신뢰하지 않는다.
             */
            $table->unsignedBigInteger('refund_amount');

            /**
             * 환불 상세 메모
             *
             * 특정 제품에 대해서만 필요한
             * 추가 환불 내용을 기록한다.
             *
             * 예:
             *
             * 2개 중 1개 품질 문제
             * 행사상품 부분 환불
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('note')
                ->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 하나의 환불 건에서 동일한 원본 판매 상세가
             * 중복 등록되는 것을 방지한다.
             *
             * 같은 sale_item에 대해 나중에 추가 환불이
             * 발생하는 것은 다른 sale_refund를 생성하여
             * 처리할 수 있다.
             */
            $table->unique([
                'sale_refund_id',
                'sale_item_id',
            ]);

            /**
             * 원본 판매 상세별 환불 이력 조회용 인덱스
             *
             * 특정 sale_item에서 지금까지
             * 몇 개가 환불되었는지 계산할 때 사용한다.
             */
            $table->index([
                'sale_item_id',
                'sale_refund_id',
            ]);
        });
    }

    /**
     * 판매 환불 상세 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_refund_items');
    }
};