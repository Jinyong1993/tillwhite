<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 월간 근무표 테이블 생성
     *
     * 직원별 날짜별 예정 근무를 관리한다.
     *
     * 근무 관리 화면에서는 이 데이터를 이용하여
     * 월간 달력, 날짜별 출근자, 주방/홀 근무자를 표시한다.
     *
     * 자동 생성된 근무표도 저장할 수 있고,
     * 헤드셰프 또는 홀 매니저가 생성 이후 직접 수정할 수도 있다.
     */
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {

            /**
             * 근무표 고유 ID
             *
             * 하나의 직원별 근무 일정을 식별하기 위한 기본키이다.
             */
            $table->id();

            /**
             * 근무 대상 직원
             *
             * 실제로 해당 날짜에 근무하는 직원이다.
             *
             * users 테이블의 id를 참조한다.
             *
             * 과거 근무표를 보존해야 하므로
             * 직원 삭제 시 자동 삭제하지 않는다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 근무 당시 점포
             *
             * 점포 직원의 경우 실제 근무 점포를 저장한다.
             *
             * 본사 직원은 특정 점포에 소속되지 않을 수 있으므로
             * NULL을 허용한다.
             *
             * 직원이 이후 다른 점포로 이동하더라도
             * 기존 근무표의 점포 정보는 변경되지 않는다.
             */
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 근무 당시 부서
             *
             * 현재 사용되는 부서:
             *
             * kitchen = 주방
             * hall = 홀
             * head_office = 본사
             *
             * 직원의 현재 부서가 변경되더라도
             * 과거 근무표의 당시 부서를 유지한다.
             */
            $table->string('department');

            /**
             * 근무 날짜
             *
             * 직원이 해당 날짜에 근무하도록
             * 예정된 날짜이다.
             */
            $table->date('work_date');

            /**
             * 근무코드
             *
             * work_codes 테이블의 근무코드를 참조한다.
             *
             * 예:
             * A
             * B
             * C
             *
             * 휴무나 휴가처럼 근무코드가 없는 일정도
             * 표현할 수 있도록 nullable로 관리한다.
             */
            $table->foreignId('work_code_id')
                ->nullable()
                ->constrained('work_codes')
                ->restrictOnDelete();

            /**
             * 예정 출근시간
             *
             * 근무표가 생성되는 시점의
             * 예정 출근시간을 저장한다.
             *
             * work_codes의 시간이 나중에 변경되어도
             * 이미 생성된 근무표에는 영향을 주지 않도록
             * 스냅샷 형태로 저장한다.
             */
            $table->time('scheduled_start_time')->nullable();

            /**
             * 예정 퇴근시간
             *
             * 근무표가 생성되는 시점의
             * 예정 퇴근시간을 저장한다.
             *
             * 실제 퇴근시간은 attendances 테이블에서
             * 별도로 관리한다.
             */
            $table->time('scheduled_end_time')->nullable();

            /**
             * 예정 휴게시간
             *
             * 해당 근무에 적용되는 기본 휴게시간을
             * 분 단위로 저장한다.
             *
             * 예:
             * 60 = 1시간
             * 90 = 1시간 30분
             *
             * NULL 대신 0을 사용하여
             * 근무시간 계산을 단순하게 한다.
             */
            $table->unsignedInteger('scheduled_break_minutes')
                ->default(0);

            /**
             * 근무 일정 상태
             *
             * work
             * → 정상 근무
             *
             * off
             * → 휴무
             *
             * leave
             * → 휴가
             *
             * 향후 필요한 상태가 추가될 수 있으므로
             * DB enum으로 고정하지 않고 문자열로 관리한다.
             */
            $table->string('status')->default('work');

            /**
             * 특이사항
             *
             * 특정 날짜의 근무와 관련된
             * 추가 내용을 기록한다.
             *
             * 예:
             * 오전 교육
             * 행사 지원
             * 타 점포 지원
             * 마감 담당
             */
            $table->text('note')->nullable();

            /**
             * 근무표 생성 방식
             *
             * auto
             * → 월간 자동생성 기능으로 생성
             *
             * manual
             * → 사용자가 직접 등록
             *
             * 자동생성 후 사람이 수정한 경우에도
             * 최초 생성 방식은 auto로 유지한다.
             */
            $table->string('source')->default('auto');

            /**
             * 수동 수정 여부
             *
             * 자동 생성된 근무표를
             * 헤드셰프 또는 홀 매니저가 직접 수정하면
             * true로 변경한다.
             *
             * 이후 자동 재생성할 때
             * 이미 사람이 수정한 근무표를
             * 실수로 덮어쓰지 않기 위해 사용한다.
             */
            $table->boolean('is_manually_modified')->default(false);

            /**
             * 최초 등록자
             *
             * 실제 근무자와 근무표를 작성한 직원은
             * 서로 다를 수 있다.
             *
             * 예:
             * 헤드셰프가 주방 직원의 근무표 작성
             * 홀 매니저가 홀 직원의 근무표 작성
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 해당 근무표를 마지막으로 수정한 직원이다.
             *
             * 아직 수정된 적이 없는 경우 NULL이다.
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
             * 직원별 하루 근무표 중복 방지
             *
             * 현재 시스템은 한 직원에게 하루 하나의
             * 근무 일정을 등록하는 구조로 한다.
             *
             * 따라서 동일 직원에게 동일 날짜의
             * 근무표를 여러 개 등록할 수 없다.
             */
            $table->unique(
                ['user_id', 'work_date'],
                'work_schedules_user_work_date_unique'
            );

            /**
             * 점포 / 부서 / 날짜별 조회용 인덱스
             *
             * 달력에서 특정 점포의 특정 부서 근무자를
             * 날짜별로 조회할 때 사용한다.
             */
            $table->index([
                'store_id',
                'department',
                'work_date',
            ]);

            /**
             * 직원별 월간 근무표 조회용 인덱스
             */
            $table->index([
                'user_id',
                'work_date',
            ]);
        });
    }

    /**
     * 월간 근무표 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};