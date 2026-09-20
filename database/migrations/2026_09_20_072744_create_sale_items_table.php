<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품별 판매 상세 테이블 생성
     *
     * 일일 매출표에 포함되는 제품별 판매 내역을 관리한다.
     *
     * 정상판매, 프로모션 판매, 기프트,
     * 기타 무상제공을 동일한 구조로 기록할 수 있다.
     *
     * 제품의 현재 가격이나 프로모션 정보가
     * 나중에 변경되더라도 과거 매출이 변하지 않도록
     * 판매 당시 가격과 금액을 스냅샷으로 저장한다.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {

            /**
             * 판매 상세 고유 ID
             */
            $table->id();

            /**
             * 판매 상세가 속한 일일 매출표
             *
             * sales.id를 참조한다.
             *
             * sale_items는 일일 매출표에 종속되는 데이터이므로
             * 해당 sales가 실제 삭제되는 경우 함께 삭제한다.
             *
             * 단, 확정된 매출 자체의 삭제 가능 여부는
             * Laravel 권한 및 업무 규칙에서 엄격하게 제한한다.
             */
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            /**
             * 판매된 제품
             *
             * products.id를 참조한다.
             *
             * 과거 판매 이력이 존재하는 제품은
             * 물리적으로 삭제할 수 없도록 제한한다.
             *
             * Laravel에서 sales의 store_id와
             * 제품의 store_id가 동일한지도 검증한다.
             */
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            /**
             * 판매 구분
             *
             * 예:
             *
             * normal
             * → 정상 판매
             *
             * promotion
             * → 프로모션 판매
             *
             * gift
             * → 기프트 / 증정
             *
             * complimentary
             * → 서비스 등 기타 무상 제공
             *
             * DB enum으로 고정하지 않고 문자열로 저장한다.
             *
             * 허용 가능한 값과 각 유형별 검증 규칙은
             * Laravel에서 관리한다.
             */
            $table->string('sale_type');

            /**
             * 적용된 프로모션
             *
             * 프로모션 판매인 경우
             * promotions.id를 저장한다.
             *
             * 정상판매, 기프트, 기타 무상제공 등
             * 프로모션과 관계없는 경우 NULL이다.
             *
             * 프로모션 판매일 경우 반드시 값이 존재하도록
             * Laravel에서 조건부 검증한다.
             */
            $table->foreignId('promotion_id')
                ->nullable()
                ->constrained('promotions')
                ->restrictOnDelete();

            /**
             * 판매 수량
             *
             * 해당 판매 유형으로 처리된
             * 제품의 수량을 저장한다.
             *
             * 0보다 큰 정수만 허용하도록
             * Laravel에서 검증한다.
             */
            $table->unsignedInteger('quantity');

            /**
             * 판매 당시 정상 단가
             *
             * product_prices에서 해당 영업일에 적용되는
             * 정상 판매가격을 조회하여 저장한다.
             *
             * 이후 제품 가격이 변경되더라도
             * 과거 판매 당시 정상가격은 유지된다.
             */
            $table->unsignedInteger('regular_unit_price');

            /**
             * 실제 적용 단가
             *
             * 고객에게 실제로 적용된
             * 제품 1개당 판매가격을 저장한다.
             *
             * 정상판매:
             * 정상 단가와 동일
             *
             * 프로모션:
             * 할인 적용 후 단가
             *
             * 기프트 / 무상제공:
             * 0
             */
            $table->unsignedInteger('actual_unit_price');

            /**
             * 정상가격 기준 총금액
             *
             * regular_unit_price × quantity 결과를
             * 판매 당시의 금액 스냅샷으로 저장한다.
             *
             * 금액 계산 자체는 Laravel에서 수행한다.
             */
            $table->unsignedBigInteger('gross_amount');

            /**
             * 할인 또는 무상제공 금액
             *
             * gross_amount와 실제 매출의 차이를 저장한다.
             *
             * 예:
             *
             * 정상가 6,000원 × 2개 = 12,000원
             * 실제 판매 9,000원
             *
             * discount_amount = 3,000원
             *
             * 기프트로 2개 모두 제공했다면
             * discount_amount = 12,000원
             */
            $table->unsignedBigInteger('discount_amount')
                ->default(0);

            /**
             * 실제 매출 금액
             *
             * actual_unit_price × quantity 결과를 저장한다.
             *
             * 정상판매 및 프로모션 판매는
             * 실제 발생한 매출금액이 저장된다.
             *
             * 기프트와 무상제공은 0원이 된다.
             */
            $table->unsignedBigInteger('net_amount');

            /**
             * 판매 상세 메모
             *
             * 해당 제품 판매에 대한
             * 특이사항을 기록할 때 사용한다.
             *
             * 예:
             *
             * VIP 고객 증정
             * 행사 추가 증정
             * 고객 컴플레인 서비스 제공
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
             * 일일 매출표에서 제품별 판매내역을
             * 빠르게 조회하기 위한 인덱스
             */
            $table->index([
                'sale_id',
                'product_id',
            ]);

            /**
             * 판매 유형별 통계 조회용 인덱스
             *
             * 정상판매, 프로모션, 기프트,
             * 무상제공 등의 수량과 금액을
             * 집계할 때 사용한다.
             */
            $table->index([
                'sale_type',
                'product_id',
            ]);

            /**
             * 프로모션별 판매 실적 조회용 인덱스
             */
            $table->index([
                'promotion_id',
                'product_id',
            ]);
        });
    }

    /**
     * 제품별 판매 상세 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};