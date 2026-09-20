<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 희망 휴무 신청 테이블 생성
     *
     * 직원이 월 근무표 작성 전에
     * 자신이 쉬고 싶은 날짜를 요청하는 기능을 관리한다.
     *
     * 희망 휴무는 연차나 보유 휴무를 사용하는 개념이 아니며,
     * 근무표 자동 생성 시 해당 직원에게 근무를 배정하지 않도록
     * 요청하는 스케줄상의 희망사항이다.
     *
     * 하나의 신청에서 여러 날짜를 요청할 수 있으며,
     * 실제 요청 날짜는 day_off_request_dates에서 관리한다.
     */
    public function up(): void
    {
        Schema::create('day_off_requests', function (Blueprint $table) {

            /**
             * 희망 휴무 신청 고유 ID
             */
            $table->id();

            /**
             * 신청 직원
             *
             * 희망 휴무를 요청한 직원의
             * users.id를 참조한다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 신청 대상 연도
             *
             * 어떤 월의 근무표에 반영할
             * 희망 휴무 신청인지 구분한다.
             *
             * 예:
             *
             * 2026년 10월 근무표
             * → target_year = 2026
             * → target_month = 10
             */
            $table->unsignedSmallInteger('target_year');

            /**
             * 신청 대상 월
             *
             * 1부터 12까지의 값을 사용하며
             * Laravel Validation에서 범위를 검증한다.
             */
            $table->unsignedTinyInteger('target_month');

            /**
             * 신청 사유
             *
             * 희망 휴무 신청 전체에 대한
             * 설명이 필요한 경우 작성한다.
             *
             * 희망 휴무 자체는 단순 날짜 요청일 수도 있으므로
             * 필수 입력으로 강제하지 않고 NULL을 허용한다.
             */
            $table->text('reason')->nullable();

            /**
             * 신청 처리 상태
             *
             * pending
             * → 승인 대기
             *
             * approved
             * → 승인
             *
             * rejected
             * → 반려
             *
             * cancelled
             * → 직원이 신청 취소
             *
             * 허용되는 값은 Laravel Validation에서 검증한다.
             */
            $table->string('status')->default('pending');

            /**
             * 신청 처리자
             *
             * 희망 휴무 신청을 승인하거나 반려한
             * 관리자의 users.id를 저장한다.
             *
             * 아직 처리되지 않은 신청은
             * 처리자가 없으므로 NULL을 허용한다.
             */
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 신청 처리 일시
             *
             * 관리자가 신청을 승인하거나
             * 반려한 실제 시간을 저장한다.
             */
            $table->timestamp('reviewed_at')->nullable();

            /**
             * 처리 의견
             *
             * 관리자가 승인 또는 반려하면서
             * 필요한 설명을 남길 수 있다.
             *
             * 예:
             *
             * 승인
             * 해당 날짜 인원 부족으로 일부 승인 불가
             */
            $table->text('review_note')->nullable();

            /**
             * 신청 취소 일시
             *
             * 직원이 신청을 취소한 경우
             * 취소된 실제 시간을 저장한다.
             */
            $table->timestamp('cancelled_at')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 직원별 월 희망 휴무 신청 조회용 인덱스
             */
            $table->index([
                'user_id',
                'target_year',
                'target_month',
            ]);

            /**
             * 월별 승인 대기 신청 조회용 인덱스
             *
             * 관리자가 특정 월의 희망 휴무 신청을
             * 모아서 처리할 때 사용한다.
             */
            $table->index([
                'target_year',
                'target_month',
                'status',
            ]);
        });
    }

    /**
     * 희망 휴무 신청 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('day_off_requests');
    }
};