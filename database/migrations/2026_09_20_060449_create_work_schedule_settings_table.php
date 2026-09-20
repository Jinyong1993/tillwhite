<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 점포 / 부서별 근무표 자동생성 설정 테이블
     *
     * 월간 근무표를 자동으로 생성할 때 사용하는
     * 점포 및 부서별 공통 규칙을 관리한다.
     *
     * 직원 개인별 A/B/C 배정 가중치는
     * work_schedule_rules에서 관리하고,
     *
     * 이 테이블에서는 해당 점포와 부서 전체에 적용되는
     * 공통적인 근무표 생성 조건을 관리한다.
     *
     * 예:
     *
     * 무역점 / 주방
     * → 오픈 최소 2명
     * → 마감 최소 2명
     * → 최대 연속근무 5일
     *
     * 무역점 / 홀
     * → 오픈 최소 1명
     * → 마감 최소 1명
     */
    public function up(): void
    {
        Schema::create('work_schedule_settings', function (Blueprint $table) {

            /**
             * 설정 고유 ID
             *
             * 하나의 점포/부서별 자동생성 설정을
             * 식별하기 위한 기본키이다.
             */
            $table->id();

            /**
             * 설정이 적용되는 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 현재 근무표 자동생성은 실제 점포 단위로 관리하므로
             * 반드시 점포를 지정한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            /**
             * 설정이 적용되는 부서
             *
             * 현재 사용 값:
             *
             * kitchen
             * → 주방
             *
             * hall
             * → 홀
             *
             * 본사(head_office)는 현재 점포별 자동생성 대상에서
             * 제외한다.
             */
            $table->string('department');

            /**
             * 오픈 근무 최소 인원
             *
             * 해당 점포/부서에서 하루에 필요한
             * 최소 오픈 근무자 수를 저장한다.
             *
             * 예:
             * 2
             *
             * 주방의 경우 하루에 최소 2명의
             * 오픈 근무자가 필요하다면 2를 입력한다.
             *
             * 별도의 최소 오픈 인원 조건이 없는 경우
             * 0을 사용한다.
             */
            $table->unsignedInteger('minimum_opening_staff')
                ->default(0);

            /**
             * 마감 근무 최소 인원
             *
             * 해당 점포/부서에서 하루에 필요한
             * 최소 마감 근무자 수를 저장한다.
             *
             * 예:
             * 2
             *
             * 마감 인원 조건이 없는 경우 0을 사용한다.
             */
            $table->unsignedInteger('minimum_closing_staff')
                ->default(0);

            /**
             * 최대 연속 근무일
             *
             * 직원에게 허용할 최대 연속 근무일 수이다.
             *
             * 예:
             * 5
             *
             * 최대 5일 연속 근무 후에는
             * 자동생성 과정에서 휴무를 고려하도록 한다.
             *
             * 해당 제한을 사용하지 않는 경우
             * NULL을 사용할 수 있다.
             */
            $table->unsignedInteger('maximum_consecutive_work_days')
                ->nullable();

            /**
             * 자동생성 사용 여부
             *
             * true(1)
             * → 해당 점포/부서에서 자동 근무표 생성 사용
             *
             * false(0)
             * → 자동생성하지 않고 수동으로 관리
             */
            $table->boolean('auto_generation_enabled')
                ->default(true);

            /**
             * 설정 메모
             *
             * 자동생성 규칙에 대한 관리자 메모를 저장한다.
             *
             * 예:
             * 주방은 오픈/마감 각각 최소 2명 유지
             * 주말에는 인원을 추가 배치
             *
             * 현재 자동생성 로직이 직접 사용하는 데이터는 아니며
             * 관리자가 설정을 확인할 때 사용할 수 있다.
             */
            $table->text('note')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 점포 + 부서 중복 방지
             *
             * 하나의 점포에는 같은 부서의
             * 자동생성 설정을 하나만 둔다.
             *
             * 예:
             *
             * 무역점 / kitchen → 1개
             * 무역점 / hall → 1개
             *
             * 같은 조합을 여러 개 등록할 수 없다.
             */
            $table->unique([
                'store_id',
                'department',
            ]);
        });
    }

    /**
     * 점포 / 부서별 근무표 자동생성 설정 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_settings');
    }
};