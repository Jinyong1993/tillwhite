<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 감사 로그 테이블 생성
     *
     * 시스템에서 발생하는 중요한 데이터 변경 이력을
     * 공통 구조로 기록한다.
     *
     * 직원, 권한, 근무, 생산·폐기, 제품, 레시피,
     * 매출, 점포, 시스템 설정 등 여러 기능에서
     * 동일한 감사 로그 구조를 사용할 수 있다.
     *
     * 감사 로그는 일반적인 업무 데이터처럼
     * 수정하거나 삭제하는 것을 전제로 하지 않는다.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {

            /**
             * 감사 로그 고유 ID
             */
            $table->id();

            /**
             * 작업을 수행한 사용자
             *
             * users.id를 참조한다.
             *
             * 시스템 자동처리처럼 특정 사용자가 없는
             * 작업도 기록할 수 있도록 NULL을 허용한다.
             *
             * 사용자 퇴사 이후에도 감사 로그가
             * 유지되어야 하므로 삭제를 제한한다.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 기능 영역
             *
             * 어떤 업무 영역에서 발생한 작업인지 저장한다.
             *
             * 예:
             *
             * employee
             * schedule
             * attendance
             * production
             * product
             * recipe
             * sales
             * store
             * system
             *
             * DB enum으로 고정하지 않고 문자열로 저장하여
             * 새로운 기능이 추가되어도 확장할 수 있게 한다.
             */
            $table->string('domain');

            /**
             * 수행된 작업
             *
             * 어떤 종류의 변경이 발생했는지 저장한다.
             *
             * 예:
             *
             * create
             * update
             * delete
             * confirm
             * cancel
             * approve
             * reject
             * login
             *
             * 허용 가능한 값은 Laravel에서 관리한다.
             */
            $table->string('action');

            /**
             * 변경 대상 종류
             *
             * 어떤 데이터가 변경되었는지 구분한다.
             *
             * Laravel 모델 클래스 전체 이름을 직접 저장하기보다
             * 안정적인 식별 문자열을 사용하는 방향으로 관리한다.
             *
             * 예:
             *
             * user
             * work_schedule
             * product
             * recipe
             * sale
             * system_setting
             */
            $table->string('target_type')
                ->nullable();

            /**
             * 변경 대상 ID
             *
             * 변경된 실제 데이터의 ID를 저장한다.
             *
             * 모든 감사 로그가 특정 DB 행을 대상으로
             * 하는 것은 아니므로 NULL을 허용한다.
             *
             * 여러 테이블의 ID를 공통으로 저장하므로
             * 특정 테이블에 FK를 연결하지 않는다.
             */
            $table->unsignedBigInteger('target_id')
                ->nullable();

            /**
             * 변경 전 데이터
             *
             * update, delete 등의 작업이 발생하기 전
             * 주요 데이터를 JSON 형태로 저장한다.
             *
             * 단순 조회나 생성 작업처럼
             * 이전 값이 없는 경우 NULL이다.
             */
            $table->json('old_values')
                ->nullable();

            /**
             * 변경 후 데이터
             *
             * create, update 등의 작업 이후
             * 주요 데이터를 JSON 형태로 저장한다.
             *
             * 삭제 작업처럼 이후 값이 없는 경우
             * NULL로 저장할 수 있다.
             */
            $table->json('new_values')
                ->nullable();

            /**
             * 감사 로그 추가 설명
             *
             * 작업에 대한 부가적인 정보가 필요한 경우 사용한다.
             *
             * 예:
             *
             * 매출 확정 취소
             * 직원 점포 이동
             * 관리자에 의한 수동 근태 수정
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('description')
                ->nullable();

            /**
             * 요청 IP 주소
             *
             * 웹 요청에서 작업이 발생한 경우
             * 요청자의 IP 주소를 기록할 수 있다.
             *
             * IPv4뿐만 아니라 IPv6도 저장할 수 있도록
             * 충분한 길이를 사용한다.
             *
             * 시스템 자동 작업에서는 NULL일 수 있다.
             */
            $table->string('ip_address', 45)
                ->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * 실제 애플리케이션에서는 감사 로그를
             * 생성 후 수정하지 않는 것을 원칙으로 한다.
             *
             * created_at은 작업 발생 시각으로 사용한다.
             */
            $table->timestamps();

            /**
             * 사용자별 감사 로그 조회용 인덱스
             */
            $table->index([
                'user_id',
                'created_at',
            ]);

            /**
             * 기능 영역 및 작업별 조회용 인덱스
             */
            $table->index([
                'domain',
                'action',
                'created_at',
            ]);

            /**
             * 특정 데이터의 전체 변경 이력을
             * 조회하기 위한 인덱스
             */
            $table->index([
                'target_type',
                'target_id',
                'created_at',
            ]);
        });
    }

    /**
     * 감사 로그 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};