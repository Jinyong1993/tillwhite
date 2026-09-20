<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 휴무 및 연차 신청 테이블 생성
     *
     * 직원이 휴무, 연차, 반차를 신청하고
     * 관리자가 승인 또는 반려하는 과정을 관리한다.
     *
     * 신청 정보와 실제 휴무/연차 잔액의 증감은
     * 서로 다른 역할이므로 분리하여 관리한다.
     *
     * 신청이 승인되면 Laravel 서비스에서
     * leave_transactions에 실제 사용 내역을 생성한다.
     */
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {

            /**
             * 휴무/연차 신청 고유 ID
             */
            $table->id();

            /**
             * 신청 직원
             *
             * 휴무 또는 연차를 신청한
             * 직원의 users.id를 참조한다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 신청 종류
             *
             * off
             * → 일반 휴무
             *
             * annual_leave
             * → 연차
             *
             * 반차는 별도의 휴가 종류가 아니라
             * annual_leave와 day_unit을 조합하여 표현한다.
             *
             * 예:
             *
             * annual_leave + full_day
             * → 연차 1일
             *
             * annual_leave + half_am
             * → 오전 반차
             *
             * annual_leave + half_pm
             * → 오후 반차
             */
            $table->string('leave_type');

            /**
             * 사용 단위
             *
             * full_day
             * → 하루 전체 사용
             *
             * half_am
             * → 오전 반차
             *
             * half_pm
             * → 오후 반차
             *
             * 허용되는 값은 Laravel Validation에서
             * 검증한다.
             */
            $table->string('day_unit')->default('full_day');

            /**
             * 신청 시작일
             *
             * 하루 신청 또는 여러 날짜에 걸친
             * 휴무/연차 신청의 시작 날짜이다.
             */
            $table->date('start_date');

            /**
             * 신청 종료일
             *
             * 하루 신청인 경우 start_date와
             * 동일한 날짜를 저장한다.
             *
             * 여러 날짜를 연속으로 신청하는 경우
             * 마지막 사용 날짜를 저장한다.
             *
             * 반차는 하루 단위이므로
             * start_date와 end_date가 같아야 하며
             * Laravel에서 이를 검증한다.
             */
            $table->date('end_date');

            /**
             * 실제 차감 수량
             *
             * 승인될 경우 사용될 휴무 또는
             * 연차의 총 일수를 저장한다.
             *
             * 예:
             *
             * 하루 → 1.00
             * 반차 → 0.50
             * 3일 연차 → 3.00
             *
             * 단순 날짜 차이로 계산하면
             * 매장 휴무일이나 근무표 등의 영향을
             * 제대로 반영하지 못할 수 있으므로
             * 최종 수량은 Laravel 서비스에서 계산한다.
             */
            $table->decimal('amount', 5, 2);

            /**
             * 신청 사유
             *
             * 직원이 휴무 또는 연차를
             * 신청하는 구체적인 사유를 작성한다.
             */
            $table->text('reason');

            /**
             * 신청 처리 상태
             *
             * pending
             * → 승인 대기
             *
             * approved
             * → 승인 완료
             *
             * rejected
             * → 반려
             *
             * cancelled
             * → 신청자가 신청 취소
             *
             * 허용되는 상태값은
             * Laravel Validation에서 검증한다.
             */
            $table->string('status')->default('pending');

            /**
             * 신청 처리자
             *
             * 신청을 승인하거나 반려한
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
             * 관리자가 승인 또는 반려한
             * 실제 시간을 저장한다.
             *
             * 승인 대기 상태에서는 NULL이다.
             */
            $table->timestamp('reviewed_at')->nullable();

            /**
             * 승인/반려 처리 의견
             *
             * 관리자가 신청을 처리하면서
             * 남기는 설명을 저장한다.
             *
             * 예:
             *
             * 승인합니다.
             * 해당 날짜 인원 부족으로 반려합니다.
             */
            $table->text('review_note')->nullable();

            /**
             * 신청 취소 일시
             *
             * 직원이 자신의 신청을 취소한
             * 시간을 저장한다.
             *
             * 취소되지 않은 신청에서는 NULL이다.
             */
            $table->timestamp('cancelled_at')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at은 실제 신청이 등록된
             * 시각으로도 사용할 수 있다.
             */
            $table->timestamps();

            /**
             * 직원별 휴무/연차 신청 조회용 인덱스
             *
             * 특정 직원의 신청 내역을
             * 날짜순으로 조회할 때 사용한다.
             */
            $table->index([
                'user_id',
                'start_date',
            ]);

            /**
             * 직원별 신청 처리 상태 조회용 인덱스
             *
             * 특정 직원의 승인 대기, 승인, 반려 등의
             * 신청을 조회할 때 사용한다.
             */
            $table->index([
                'user_id',
                'status',
                'start_date',
            ]);

            /**
             * 승인 대기 신청 조회용 인덱스
             *
             * 관리자가 처리해야 하는 신청을
             * 날짜별로 조회할 때 사용한다.
             */
            $table->index([
                'status',
                'start_date',
            ]);
        });
    }

    /**
     * 휴무 및 연차 신청 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};