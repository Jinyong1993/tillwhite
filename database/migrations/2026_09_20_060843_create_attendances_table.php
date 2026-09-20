<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 출결 기록 테이블 생성
     *
     * 직원의 실제 출퇴근 기록과
     * 근무표를 기준으로 계산된 인정 근무시간을 관리한다.
     *
     * 출퇴근은 점포의 QR을 통해 진행하며,
     * 모바일 기기의 GPS 위치를 이용하여
     * 실제 점포에서 출퇴근했는지 검증한다.
     *
     * 실제 출퇴근 시간과 인정되는 근무시간은
     * 서로 다른 값으로 관리한다.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {

            /**
             * 출결 고유 ID
             *
             * 하나의 출퇴근 기록을 식별하는 기본키이다.
             */
            $table->id();

            /**
             * 출결 대상 직원
             *
             * 실제 출퇴근하는 직원의 users.id를 참조한다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 해당 출결과 연결된 근무표
             *
             * work_schedules에서 해당 날짜의
             * 예정 근무시간과 근무코드를 확인할 수 있다.
             *
             * 하나의 근무표에는 하나의 출결 기록만
             * 연결되도록 관리한다.
             */
            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules')
                ->restrictOnDelete();

            /**
             * 근무 점포
             *
             * 실제 출퇴근이 이루어진 점포를 저장한다.
             *
             * 근무표의 store_id와 별도로 저장하여
             * 출결 당시 점포 정보를 보존한다.
             *
             * 본사 직원이나 별도 근무의 경우 NULL을 허용한다.
             */
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 예정 출근시간
             *
             * 출결 생성 당시 근무표에 설정되어 있던
             * 예정 출근시간을 저장한다.
             *
             * 근무표가 이후 수정되더라도
             * 해당 출결 기록의 기준시간을 유지하기 위한 값이다.
             */
            $table->dateTime('scheduled_start_at')->nullable();

            /**
             * 예정 퇴근시간
             *
             * 출결 생성 당시 근무표에 설정되어 있던
             * 예정 퇴근시간을 저장한다.
             *
             * 조퇴 여부 및 연장근무 계산의
             * 기준으로 사용한다.
             */
            $table->dateTime('scheduled_end_at')->nullable();

            /**
             * 실제 출근시간
             *
             * 직원이 점포 QR을 통해
             * 실제 출근을 인증한 시간이다.
             *
             * 실제 QR 인증시간을 그대로 보존한다.
             */
            $table->dateTime('actual_check_in_at')->nullable();

            /**
             * 실제 퇴근시간
             *
             * 직원이 점포 QR을 통해
             * 실제 퇴근을 인증한 시간이다.
             *
             * 조퇴 또는 연장근무 여부와 관계없이
             * 실제 QR 인증시간을 그대로 보존한다.
             */
            $table->dateTime('actual_check_out_at')->nullable();

            /**
             * 인정 출근시간
             *
             * 회사의 출결 계산 기준으로
             * 인정되는 출근시간이다.
             *
             * 구체적인 출근시간 인정 규칙은
             * Laravel 서비스에서 처리한다.
             */
            $table->dateTime('recognized_start_at')->nullable();

            /**
             * 인정 퇴근시간
             *
             * 회사의 출결 계산 기준으로
             * 인정되는 퇴근시간이다.
             *
             * 조퇴:
             *
             * 예정 퇴근 18:00
             * 실제 퇴근 17:20
             * 인정 퇴근 18:00
             *
             * 연장근무:
             *
             * 예정 퇴근 18:00
             * 실제 퇴근 19:37
             * 인정 퇴근 19:30
             *
             * 연장근무는 15분 단위로 내림하여 인정한다.
             */
            $table->dateTime('recognized_end_at')->nullable();

            /**
             * 인정 휴게시간
             *
             * 해당 근무에서 인정되는 휴게시간을
             * 분 단위로 저장한다.
             *
             * 근무표의 scheduled_break_minutes를
             * 출결 기준으로 복사하여 보존한다.
             */
            $table->unsignedInteger('recognized_break_minutes')
                ->default(0);

            /**
             * 총 인정 연장근무시간
             *
             * 예정 퇴근시간 이후 발생한 연장근무를
             * 15분 단위로 내림하여 분 단위로 저장한다.
             *
             * 예:
             *
             * 18:05 → 0분
             * 18:14 → 0분
             * 18:15 → 15분
             * 18:29 → 15분
             * 18:30 → 30분
             * 19:37 → 90분
             */
            $table->unsignedInteger('overtime_minutes')
                ->default(0);

            /**
             * 출근 인증 방식
             *
             * 현재는 점포 QR을 이용한
             * qr 방식을 사용한다.
             *
             * 향후 관리자 수동 처리 등이 추가될 수 있으므로
             * 문자열로 관리한다.
             */
            $table->string('check_in_method')->nullable();

            /**
             * 퇴근 인증 방식
             *
             * 현재는 점포 QR을 이용한
             * qr 방식을 사용한다.
             *
             * 향후 관리자 수동 처리 등이 추가될 수 있으므로
             * 문자열로 관리한다.
             */
            $table->string('check_out_method')->nullable();

            /**
             * 출근 당시 GPS 위도
             *
             * 출근 QR 인증 순간에만 위치를 확인하며,
             * 직원의 위치를 지속적으로 추적하지 않는다.
             */
            $table->decimal('check_in_latitude', 10, 7)->nullable();

            /**
             * 출근 당시 GPS 경도
             */
            $table->decimal('check_in_longitude', 10, 7)->nullable();

            /**
             * 출근 당시 점포와의 거리
             *
             * 직원의 GPS 위치와 점포 위치 사이의
             * 거리를 미터 단위로 저장한다.
             */
            $table->decimal('check_in_distance_meters', 8, 2)
                ->nullable();

            /**
             * 출근 위치 검증 결과
             *
             * true:
             * 점포 허용 범위 안에서 인증됨
             *
             * false:
             * 허용 범위 밖이거나 위치 검증 실패
             */
            $table->boolean('check_in_location_verified')
                ->default(false);

            /**
             * 퇴근 당시 GPS 위도
             *
             * 퇴근 QR 인증 순간의 위치를 저장한다.
             */
            $table->decimal('check_out_latitude', 10, 7)->nullable();

            /**
             * 퇴근 당시 GPS 경도
             */
            $table->decimal('check_out_longitude', 10, 7)->nullable();

            /**
             * 퇴근 당시 점포와의 거리
             *
             * 직원의 GPS 위치와 점포 위치 사이의
             * 거리를 미터 단위로 저장한다.
             */
            $table->decimal('check_out_distance_meters', 8, 2)
                ->nullable();

            /**
             * 퇴근 위치 검증 결과
             *
             * true:
             * 점포 허용 범위 안에서 인증됨
             *
             * false:
             * 허용 범위 밖이거나 위치 검증 실패
             */
            $table->boolean('check_out_location_verified')
                ->default(false);

            /**
             * 출결 상태
             *
             * 예:
             *
             * normal
             * late
             * early_leave
             * overtime
             * absent
             * leave
             *
             * 실제 출결 상태는 Laravel에서 계산한다.
             */
            $table->string('status')->default('normal');

            /**
             * 조퇴 사유
             *
             * 예정 퇴근시간보다 실제 QR 퇴근시간이
             * 빠른 경우 직원이 반드시 선택한다.
             *
             * 예:
             *
             * work_finished
             * → 업무가 일찍 끝남
             *
             * illness
             * → 건강상의 이유
             *
             * manager_instruction
             * → 헤드셰프 또는 관리자 지시
             *
             * personal
             * → 개인 사정
             *
             * half_day_leave
             * → 반차 사용
             *
             * other
             * → 기타
             *
             * DB에서는 NULL을 허용한다.
             *
             * 조퇴일 경우에만 Laravel에서 필수로 검증한다.
             *
             * 정상퇴근, 연장근무 또는 결근에서는
             * 값이 없어도 오류가 발생하지 않는다.
             */
            $table->string('early_leave_reason')->nullable();

            /**
             * 조퇴 상세 사유
             *
             * 조퇴가 발생한 구체적인 이유를
             * 직원이 직접 텍스트로 입력한다.
             *
             * 조퇴일 경우에만
             * early_leave_reason과 함께 필수로 검증한다.
             *
             * 반차 사용을 선택한 경우에도
             * 상세 사유를 입력하도록 한다.
             *
             * 정상퇴근, 연장근무 또는 결근에서는
             * 값이 없어도 오류가 발생하지 않는다.
             */
            $table->text('early_leave_note')->nullable();

            /**
             * 연장근무 사유
             *
             * 15분 이상의 인정 연장근무가 발생한 경우
             * 직원이 반드시 선택한다.
             *
             * 예:
             *
             * closing_work
             * → 마감 작업이 많이 남음
             *
             * busy
             * → 업무가 바빴음
             *
             * increased_production
             * → 생산량 증가
             *
             * store_support
             * → 매장 업무 지원
             *
             * manager_request
             * → 관리자 요청
             *
             * other
             * → 기타
             *
             * DB에서는 NULL을 허용한다.
             *
             * 인정 연장근무가 15분 이상인 경우에만
             * Laravel에서 필수로 검증한다.
             *
             * 정상퇴근, 조퇴 또는 결근에서는
             * 값이 없어도 오류가 발생하지 않는다.
             */
            $table->string('overtime_reason')->nullable();

            /**
             * 연장근무 상세 사유
             *
             * 연장근무가 발생한 구체적인 이유를
             * 직원이 직접 텍스트로 입력한다.
             *
             * 인정 연장근무가 15분 이상인 경우에만
             * overtime_reason과 함께 필수로 검증한다.
             *
             * 정상퇴근, 조퇴 또는 결근에서는
             * 값이 없어도 오류가 발생하지 않는다.
             */
            $table->text('overtime_note')->nullable();

            /**
             * 결근 유형
             *
             * 근무표상 근무 예정이었지만
             * 실제로 출근하지 않은 경우 결근 유형을 저장한다.
             *
             * 예:
             *
             * notified
             * → 사전 또는 당일 연락 후 결근
             *
             * unexcused
             * → 별도의 연락 없이 무단결근
             *
             * other
             * → 기타 결근
             *
             * DB에서는 NULL을 허용한다.
             *
             * status가 absent인 경우에만
             * Laravel에서 필수로 검증한다.
             *
             * 정상근무, 지각, 조퇴, 연장근무 등에서는
             * 값이 없어도 오류가 발생하지 않는다.
             */
            $table->string('absence_type')->nullable();

            /**
             * 결근 상세 사유
             *
             * 결근이 발생한 구체적인 이유를 저장한다.
             *
             * 예:
             *
             * 당일 몸 상태 악화로 출근 불가
             * 가족 긴급 상황
             * 연락 없이 출근하지 않음
             *
             * DB에서는 NULL을 허용한다.
             *
             * status가 absent인 경우에만
             * absence_type과 함께 Laravel에서 필수로 검증한다.
             *
             * 결근이 아닌 출결에서는
             * 값이 없어도 오류가 발생하지 않는다.
             */
            $table->text('absence_reason')->nullable();

            /**
             * 관리자 또는 직원의 일반 출결 특이사항
             *
             * 예:
             *
             * QR 오류
             * GPS 오류
             * 관리자 수동 처리
             * 외부 업무
             *
             * 조퇴, 연장근무 및 결근 사유와는
             * 별도로 관리한다.
             */
            $table->text('note')->nullable();

            /**
             * 관리자가 수동으로 수정한 출결인지 여부
             *
             * 출결 오류나 QR/GPS 문제 등이 발생했을 때
             * 권한이 있는 관리자가 직접 수정할 수 있도록 한다.
             *
             * 수정된 출결은 자동 계산으로 다시 덮어쓰지 않도록
             * 구분할 수 있다.
             */
            $table->boolean('is_manually_modified')
                ->default(false);

            /**
             * 출결 최초 등록자
             *
             * 일반적인 QR 출결에서는 실제 직원이 되며,
             * 관리자 수동 등록의 경우 관리자가 될 수 있다.
             *
             * 자동 결근 처리처럼 시스템이 생성하는 경우를
             * 고려하여 NULL을 허용한다.
             */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 관리자가 출결을 수정한 경우
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
             * 하나의 근무표에는 하나의 출결 기록만 생성한다.
             *
             * 출퇴근을 여러 번 찍더라도
             * 동일 근무표에 대한 출결 데이터가
             * 여러 행으로 생성되지 않도록 한다.
             */
            $table->unique(
                'work_schedule_id',
                'attendances_work_schedule_unique'
            );

            /**
             * 직원별 월간 출결 조회용 인덱스
             */
            $table->index([
                'user_id',
                'scheduled_start_at',
            ]);

            /**
             * 점포별 출결 조회용 인덱스
             */
            $table->index([
                'store_id',
                'scheduled_start_at',
            ]);

            /**
             * 출결 상태별 조회용 인덱스
             */
            $table->index([
                'status',
                'scheduled_start_at',
            ]);
        });
    }

    /**
     * 출결 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};