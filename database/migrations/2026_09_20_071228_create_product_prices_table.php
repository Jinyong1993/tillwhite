<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품 판매가격 이력 테이블 생성
     *
     * 제품별 정상 판매가격과 가격 적용 기간을 관리한다.
     *
     * products 테이블에 현재 가격을 직접 저장하지 않고
     * 가격을 별도 이력 테이블로 관리하여
     * 가격이 변경되더라도 과거 가격 정보를 보존한다.
     *
     * 실제 판매가 발생하면 판매 상세 테이블에는
     * 판매 당시의 가격을 다시 스냅샷으로 저장하여
     * 이후 제품 가격이 변경되어도
     * 과거 매출 금액이 변하지 않도록 한다.
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {

            /**
             * 제품 가격 이력 고유 ID
             */
            $table->id();

            /**
             * 가격이 적용되는 제품
             *
             * products.id를 참조한다.
             *
             * 가격 이력이 존재하는 제품은
             * 과거 가격 기록 보존을 위해
             * 물리적으로 삭제할 수 없도록 제한한다.
             */
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            /**
             * 정상 판매가격
             *
             * 해당 기간에 적용되는 제품의
             * 기본 정상 판매가격을 저장한다.
             *
             * 대한민국 원화 기준으로
             * 소수점이 필요하지 않으므로
             * unsignedInteger를 사용한다.
             *
             * 예:
             *
             * 6000
             * 12500
             * 35000
             *
             * 프로모션 가격이나 할인 가격은
             * 이 컬럼을 직접 변경하지 않고
             * 별도의 프로모션 및 판매 기록에서 처리한다.
             */
            $table->unsignedInteger('price');

            /**
             * 가격 적용 시작일
             *
             * 해당 가격이 실제 판매에 적용되기
             * 시작하는 날짜를 저장한다.
             *
             * 예:
             *
             * 2026-09-01
             */
            $table->date('effective_from');

            /**
             * 가격 적용 종료일
             *
             * 해당 가격의 적용이 끝나는 날짜를 저장한다.
             *
             * 현재 적용 중인 가격은
             * 종료일을 알 수 없으므로 NULL로 저장한다.
             *
             * 새로운 가격이 등록되면 Laravel에서
             * 기존 현재 가격의 종료일을 처리한다.
             */
            $table->date('effective_to')
                ->nullable();

            /**
             * 가격 등록자
             *
             * 해당 가격 정보를 등록한
             * users.id를 저장한다.
             *
             * 등록자가 퇴사하더라도
             * 가격 변경 이력을 보존한다.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 가격 정보 마지막 수정자
             *
             * 가격 적용일 등의 정보가 수정된 경우
             * 마지막으로 수정한 사용자를 저장한다.
             *
             * 최초 등록 이후 수정되지 않았다면 NULL이다.
             */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 같은 제품에 동일한 시작일을 가진
             * 가격 이력이 중복 생성되는 것을 방지한다.
             */
            $table->unique([
                'product_id',
                'effective_from',
            ]);

            /**
             * 특정 날짜에 적용되는 제품 가격을
             * 빠르게 조회하기 위한 인덱스
             */
            $table->index([
                'product_id',
                'effective_from',
                'effective_to',
            ]);
        });
    }

    /**
     * 제품 판매가격 이력 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};