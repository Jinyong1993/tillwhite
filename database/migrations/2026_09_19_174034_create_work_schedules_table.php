<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 근무 스케줄 테이블 생성
     *
     * 직원별 근무 날짜, 근무 점포, 담당 부서 및
     * 근무 상태를 관리한다.
     *
     * 생산 및 폐기 기록을 입력할 때 해당 직원이 실제로
     * 해당 날짜와 점포에서 근무하는 직원인지 검증하는 데 사용한다.
     *
     * 또한 향후 직원 스케줄 조회 및 관리 기능에서도
     * 동일한 데이터를 사용할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {

            /**
             * 근무 스케줄 고유 ID
             *
             * DB 내부에서 각각의 근무 스케줄을
             * 구분하기 위한 기본키(PK)이다.
             */
            $table->id();

            /**
             * 근무 직원
             *
             * users 테이블의 id를 참조한다.
             *
             * 해당 날짜에 어떤 직원이 근무하는지를 나타낸다.
             */
            $table->foreignId('user_id')
                ->constrained('users');

            /**
             * 근무 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 사용자의 현재 store_id만 이용하지 않고
             * 스케줄 자체에도 당시 근무 점포를 저장한다.
             *
             * 따라서 직원이 추후 다른 점포로 이동하더라도
             * 과거에 어느 점포에서 근무했는지 정확하게 유지할 수 있다.
             */
            $table->foreignId('store_id')
                ->constrained('stores');

            /**
             * 근무 부서
             *
             * 해당 근무일에 실제로 근무하는 부서를 저장한다.
             *
             * 기본적으로 다음 값을 사용한다.
             *
             * kitchen    = 주방
             * hall       = 홀
             * operations = 운영진 / 운영관리
             *
             * 사용자의 현재 department만 참조하지 않고
             * 스케줄에도 당시 부서를 저장하여 과거 근무 이력을 보존한다.
             */
            $table->string('department');

            /**
             * 근무 날짜
             *
             * 직원이 근무하는 실제 업무 날짜이다.
             *
             * 생산 및 폐기 기록의 work_date와 비교하여
             * 해당 직원이 그날 근무 대상인지 검증할 때 사용한다.
             *
             * 시간 정보는 필요하지 않으므로 date 타입을 사용한다.
             */
            $table->date('work_date');

            /**
             * 근무 시작 시간
             *
             * 해당 직원의 예정 근무 시작 시간을 저장한다.
             *
             * 근무 날짜만 등록하고 정확한 시간이 아직 정해지지 않은
             * 경우도 허용할 수 있도록 nullable로 관리한다.
             */
            $table->time('start_time')->nullable();

            /**
             * 근무 종료 시간
             *
             * 해당 직원의 예정 근무 종료 시간을 저장한다.
             *
             * 근무 종료 시간이 아직 정해지지 않은 경우도
             * 허용할 수 있도록 nullable로 관리한다.
             */
            $table->time('end_time')->nullable();

            /**
             * 근무 상태
             *
             * 해당 스케줄의 현재 상태를 나타낸다.
             *
             * 기본적으로 다음 값을 사용할 수 있다.
             *
             * scheduled = 근무 예정
             * working   = 근무중
             * completed = 근무 완료
             * absent    = 결근
             * cancelled = 근무 취소
             *
             * 실제 생산 및 폐기 입력 허용 여부는 단순히 스케줄이
             * 존재하는지만 확인하지 않고 이 상태값도 함께 검증한다.
             */
            $table->string('status')->default('scheduled');

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 근무 스케줄이 등록된 시간
             * updated_at = 근무 스케줄이 마지막으로 수정된 시간
             */
            $table->timestamps();

            /**
             * 직원별 근무 날짜 조회를 위한 인덱스
             *
             * 생산 및 폐기 입력 시 특정 직원이 특정 날짜에
             * 근무했는지를 자주 조회하게 된다.
             *
             * user_id와 work_date를 함께 인덱스로 구성하여
             * 해당 검증 조회를 효율적으로 처리할 수 있도록 한다.
             */
            $table->index([
                'user_id',
                'work_date',
            ]);
        });
    }

    /**
     * 근무 스케줄 테이블 삭제
     *
     * Migration을 rollback할 때
     * work_schedules 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};