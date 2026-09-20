<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 프로모션 대상 제품 테이블 생성
     *
     * 하나의 프로모션에 어떤 제품이 포함되는지와
     * 각 제품에 적용할 할인 방식을 관리한다.
     *
     * 같은 프로모션에서도 제품마다
     * 서로 다른 할인 방식을 사용할 수 있도록
     * 프로모션 기본정보와 분리하여 관리한다.
     */
    public function up(): void
    {
        Schema::create('promotion_products', function (Blueprint $table) {

            /**
             * 프로모션 대상 제품 고유 ID
             */
            $table->id();

            /**
             * 적용할 프로모션
             *
             * promotions.id를 참조한다.
             *
             * 프로모션이 실제 삭제되는 경우
             * 해당 프로모션의 제품 설정도
             * 함께 삭제한다.
             */
            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();

            /**
             * 프로모션 대상 제품
             *
             * products.id를 참조한다.
             *
             * 프로모션 기록에서 사용 중인 제품은
             * 함부로 삭제되지 않도록 제한한다.
             *
             * Laravel에서는 프로모션의 적용 점포와
             * 제품의 점포가 일치하는지도 검증한다.
             *
             * 전 점포 공통 프로모션인 경우에도
             * 실제 적용되는 각 점포의 제품과 연결한다.
             */
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            /**
             * 할인 방식
             *
             * 할인 계산 방법을 저장한다.
             *
             * 예:
             *
             * fixed_amount
             * → 정상가격에서 일정 금액 할인
             *
             * percentage
             * → 정상가격에서 일정 비율 할인
             *
             * fixed_price
             * → 특정 판매가격으로 판매
             *
             * DB enum으로 고정하지 않고
             * 문자열로 저장한다.
             *
             * 실제 허용값과 계산 방식은
             * Laravel에서 관리한다.
             */
            $table->string('discount_type');

            /**
             * 할인값
             *
             * discount_type에 따라 의미가 달라진다.
             *
             * 예:
             *
             * fixed_amount + 1000
             * → 1,000원 할인
             *
             * percentage + 20
             * → 20% 할인
             *
             * fixed_price + 4500
             * → 4,500원에 판매
             *
             * 금액뿐만 아니라 할인율도 저장해야 하므로
             * decimal을 사용한다.
             *
             * Laravel에서 할인 방식에 따라
             * 허용 범위를 검증한다.
             */
            $table->decimal('discount_value', 10, 2);

            /**
             * 추가 설명
             *
             * 해당 제품의 프로모션에만 필요한
             * 안내사항이나 조건을 저장한다.
             *
             * 예:
             *
             * 오후 5시 이후 적용
             * 1인 최대 2개
             * 재고 소진 시 종료
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
             * 하나의 프로모션에 동일한 제품이
             * 중복 등록되는 것을 방지한다.
             */
            $table->unique([
                'promotion_id',
                'product_id',
            ]);

            /**
             * 제품별 프로모션 조회용 인덱스
             *
             * 특정 제품에 어떤 프로모션이
             * 연결되어 있는지 조회할 때 사용한다.
             */
            $table->index([
                'product_id',
                'promotion_id',
            ]);
        });
    }

    /**
     * 프로모션 대상 제품 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_products');
    }
};