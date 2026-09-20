<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 휴무 및 연차 거래내역 테이블 생성
     *
     * 직원별 휴무와 연차의 발생, 사용, 조정 내역을
     * 하나의 거래내역 형태로 관리한다.
     *
     * 현재 잔여 휴무 또는 잔여 연차를 직접 저장하지 않고
     * 직원별 거래내역의 amount 합계를 통해 계산한다.
     *
     * 이를 통해 잔여 수량뿐만 아니라
     * 언제, 어떤 이유로 휴무 또는 연차가
     * 발생하거나 사용되었는지 추적할 수 있다.
     */
    public function up(): void
    {
        Schema::create('leave_transactions', function (Blueprint $table) {

            /**
             * 휴무/연차 거래내역 고유 ID
             */
            $table->id();

            /**
             * 대상 직원
             *
             * 휴무 또는 연차가 발생하거나
             * 사용되는 직원의 users.id를 참조한다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 휴무 종류
             *
             * off
             * → 일반 휴무
             *
             * annual_leave
             * → 연차
             *
             * 새로운 휴가 종류가 필요한 경우
             * Laravel Validation을 확장할 수 있도록
             * enum 대신 문자열로 관리한다.
             */
            $table->string('leave_type');

            /**
             * 거래 종류
             *
             * accrual
             * → 일반적인 휴무/연차 발생
             *
             * overtime_accrual
             * → 연장근무에 의해 발생한 보상 휴무
             *
             * usage
             * → 휴무/연차 사용
             *
             * adjustment
             * → 관리자가 수동으로 수량을 조정
             *
             * leave_type과 transaction_type의
             * 올바른 조합은 Laravel에서 검증한다.
             *
             * 예:
             *
             * off + overtime_accrual
             * → 가능
             *
             * annual_leave + overtime_accrual
             * → 현재 업무 규칙에서는 허용하지 않음
             */
            $table->string('transaction_type');

            /**
             * 휴무/연차 증감 수량
             *
             * 일(day)을 기준으로 저장한다.
             *
             * 양수:
             * 휴무 또는 연차 증가
             *
             * 음수:
             * 휴무 또는 연차 사용/차감
             *
             * 예:
             *
             * +1.00 → 1일 발생
             * +0.50 → 반차 발생
             * -1.00 → 1일 사용
             * -0.50 → 반차 사용
             *
             * adjustment의 경우
             * 양수와 음수를 모두 사용할 수 있다.
             */
            $table->decimal('amount', 5, 2);

            /**
             * 거래 적용일
             *
             * 실제 휴무/연차가 발생하거나
             * 사용된 날짜를 저장한다.
             *
             * created_at과 분리하여 관리한다.
             *
             * 예:
             *
             * 9월 10일 사용한 연차를
             * 관리자가 9월 12일 등록하더라도
             * occurred_on은 9월 10일이 된다.
             */
            $table->date('occurred_on');

            /**
             * 연장근무 출결 기록
             *
             * 연장근무로 인해 보상 휴무가 발생한 경우
             * 어떤 출결 기록으로부터 발생했는지 추적한다.
             *
             * 일반 휴무 발생, 연차 발생 또는 사용 등에서는
             * 필요하지 않으므로 NULL을 허용한다.
             *
             * overtime_accrual인 경우에는
             * Laravel에서 필수로 검증한다.
             */
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained('attendances')
                ->restrictOnDelete();

            /**
             * 관련 근무표
             *
             * 휴무 또는 연차를 실제 일정에 사용한 경우
             * 어떤 근무표와 관련된 거래인지 연결할 수 있다.
             *
             * 단순 발생 또는 관리자 조정에서는
             * 필요하지 않으므로 NULL을 허용한다.
             */
            $table->foreignId('work_schedule_id')
                ->nullable()
                ->constrained('work_schedules')
                ->restrictOnDelete();

            /**
             * 거래 사유
             *
             * 휴무/연차 발생 또는 사용의
             * 간단한 사유를 저장한다.
             *
             * 예:
             *
             * 연장근무 보상 휴무 발생
             * 연차 사용
             * 관리자 수동 조정
             */
            $table->string('reason')->nullable();

            /**
             * 상세 내용
             *
             * 거래에 대한 추가 설명이 필요한 경우 사용한다.
             *
             * 예:
             *
             * 9월 연차 정기 발생
             * 잘못 등록된 휴무 1일 복구
             * 연장근무 누락분 관리자 추가
             */
            $table->text('note')->nullable();

            /**
             * 거래 최초 등록자
             *
             * 시스템 자동 발생의 경우 NULL을 허용한다.
             *
             * 관리자가 직접 등록하거나 조정한 경우에는
             * 해당 관리자의 users.id를 기록한다.
             */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 관리자가 거래내역을 수정한 경우
             * 마지막 수정자를 기록한다.
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
             * 직원별 휴무/연차 거래내역 조회용 인덱스
             *
             * 직원의 특정 기간 휴무/연차 현황을
             * 조회할 때 사용한다.
             */
            $table->index([
                'user_id',
                'occurred_on',
            ]);

            /**
             * 직원별 휴무 종류 조회용 인덱스
             *
             * 특정 직원의 일반 휴무 또는 연차 잔여량을
             * 계산할 때 사용한다.
             */
            $table->index([
                'user_id',
                'leave_type',
                'occurred_on',
            ]);

            /**
             * 거래 종류별 조회용 인덱스
             *
             * 일반 발생, 연장근무 발생, 사용, 조정 등의
             * 통계를 조회할 때 사용한다.
             */
            $table->index([
                'transaction_type',
                'occurred_on',
            ]);
        });
    }

    /**
     * 휴무 및 연차 거래내역 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_transactions');
    }
};