<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 판매 환불 테이블 생성
     *
     * 기존 판매 내역을 삭제하거나 수정하지 않고
     * 별도의 환불 기록을 생성하여 관리한다.
     *
     * 하나의 판매에서 여러 번 부분 환불이 발생할 수 있으므로
     * sales와 1:N 관계로 관리한다.
     *
     * 실제 환불 제품, 수량, 금액은
     * sale_refund_items 테이블에서 관리한다.
     */
    public function up(): void
    {
        Schema::create('sale_refunds', function (Blueprint $table) {

            /**
             * 환불 고유 ID
             */
            $table->id();

            /**
             * 환불 대상 일일 매출
             *
             * sales.id를 참조한다.
             *
             * 하나의 매출에 여러 환불 기록이
             * 존재할 수 있다.
             *
             * 원본 판매 기록은 환불 후에도
             * 그대로 보존한다.
             */
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete();

            /**
             * 실제 환불 처리일
             *
             * 판매일과 환불일이 다를 수 있으므로
             * sales.sales_date와 별도로 저장한다.
             *
             * 예:
             *
             * 9월 20일 판매
             * 9월 21일 환불
             *
             * → refund_date = 2026-09-21
             */
            $table->date('refund_date');

            /**
             * 환불 사유 구분
             *
             * 예:
             *
             * customer_request
             * → 고객 요청
             *
             * product_issue
             * → 제품 문제
             *
             * payment_error
             * → 결제 오류
             *
             * order_error
             * → 주문 처리 오류
             *
             * other
             * → 기타
             *
             * DB enum으로 고정하지 않고 문자열로 저장한다.
             * 허용 가능한 값은 Laravel에서 검증한다.
             */
            $table->string('reason_type');

            /**
             * 상세 환불 사유
             *
             * 실제 환불이 발생한 이유를 기록한다.
             *
             * 향후 환불 분석 및 이력 확인을 위해
             * 환불 등록 시 Laravel에서 필수 입력으로
             * 검증하는 방향으로 사용한다.
             */
            $table->text('reason');

            /**
             * 환불 상태
             *
             * confirmed
             * → 정상적으로 확정된 환불
             *
             * cancelled
             * → 환불 처리가 취소된 상태
             *
             * 환불 기록 자체를 삭제하지 않고
             * 상태를 변경하여 이력을 보존한다.
             *
             * DB enum으로 고정하지 않고
             * Laravel에서 상태 변경 규칙을 관리한다.
             */
            $table->string('status')
                ->default('confirmed');

            /**
             * 환불 처리자
             *
             * 실제 환불을 등록한 사용자의
             * users.id를 저장한다.
             *
             * 처리자가 이후 퇴사하더라도
             * 환불 이력을 유지한다.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 환불 사유 또는 상태 등이 수정된 경우
             * 마지막으로 수정한 사용자를 저장한다.
             *
             * 수정되지 않았다면 NULL이다.
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
             * 특정 판매의 환불 이력 조회용 인덱스
             */
            $table->index([
                'sale_id',
                'refund_date',
            ]);

            /**
             * 환불일 및 상태별 통계 조회용 인덱스
             *
             * 일별 / 월별 환불 건수와
             * 확정 환불 내역 등을 조회할 때 사용한다.
             */
            $table->index([
                'refund_date',
                'status',
            ]);

            /**
             * 환불 사유 분석용 인덱스
             *
             * 제품 문제, 고객 요청 등
             * 환불 원인별 통계를 조회할 때 사용한다.
             */
            $table->index([
                'reason_type',
                'refund_date',
            ]);
        });
    }

    /**
     * 판매 환불 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
    }
};