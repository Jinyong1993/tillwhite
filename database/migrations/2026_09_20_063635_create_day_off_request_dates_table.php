<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 희망 휴무 신청 날짜 테이블 생성
     *
     * day_off_requests에서 생성된 하나의 희망 휴무 신청에
     * 실제로 요청한 여러 날짜를 연결하여 관리한다.
     *
     * 예:
     *
     * 2026년 12월 희망 휴무 신청
     *
     * 2026-12-02
     * 2026-12-05
     * 2026-12-11
     * 2026-12-17
     *
     * 처럼 연속되지 않은 여러 날짜를
     * 하나의 월 신청에 포함할 수 있다.
     */
    public function up(): void
    {
        Schema::create('day_off_request_dates', function (Blueprint $table) {

            /**
             * 희망 휴무 신청 날짜 고유 ID
             */
            $table->id();

            /**
             * 희망 휴무 신청
             *
             * 어떤 희망 휴무 신청에 포함된 날짜인지
             * day_off_requests.id를 참조한다.
             *
             * 신청 자체가 삭제되는 경우에는
             * 해당 신청에 속한 날짜들도 함께 삭제한다.
             */
            $table->foreignId('day_off_request_id')
                ->constrained('day_off_requests')
                ->cascadeOnDelete();

            /**
             * 희망 휴무 날짜
             *
             * 직원이 실제로 쉬기를 희망하는 날짜를 저장한다.
             *
             * 해당 날짜의 연도와 월은 부모 신청인
             * day_off_requests의 target_year,
             * target_month와 일치해야 한다.
             *
             * 이 일치 여부는 Laravel에서 검증한다.
             *
             * 예:
             *
             * target_year = 2026
             * target_month = 12
             *
             * request_date = 2026-12-05
             * → 가능
             *
             * request_date = 2027-01-02
             * → 해당 신청에는 등록 불가
             * → 2027년 1월 신청을 별도로 생성
             */
            $table->date('request_date');

            /**
             * 해당 날짜에 대한 개별 메모
             *
             * 신청 전체의 사유는 day_off_requests.reason에 저장하고,
             * 특정 날짜에만 별도의 설명이 필요한 경우 사용한다.
             *
             * 예:
             *
             * 가족 행사
             * 병원 예약
             * 개인 일정
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('note')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 동일 신청에서 같은 날짜를
             * 중복으로 등록하지 못하도록 제한한다.
             *
             * 예:
             *
             * 신청 #10 + 2026-12-05
             * 신청 #10 + 2026-12-05
             *
             * 위와 같은 중복 등록을 방지한다.
             */
            $table->unique([
                'day_off_request_id',
                'request_date',
            ]);

            /**
             * 희망 휴무 날짜 조회용 인덱스
             *
             * 자동 근무표 생성 시 특정 기간에 승인된
             * 희망 휴무 날짜를 조회할 때 사용할 수 있다.
             */
            $table->index('request_date');
        });
    }

    /**
     * 희망 휴무 신청 날짜 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('day_off_request_dates');
    }
};