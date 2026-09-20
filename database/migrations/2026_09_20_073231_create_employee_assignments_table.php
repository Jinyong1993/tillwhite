<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 직원 인사발령 이력 테이블 생성
     *
     * 직원의 점포, 부서, 직급, 역할 변경 이력을 관리한다.
     *
     * users 테이블에는 현재 소속 정보를 유지하고,
     * 이 테이블에는 과거부터 현재까지의
     * 인사발령 이력을 기간 단위로 저장한다.
     *
     * 이를 통해 직원이 다른 점포로 이동하거나
     * 부서, 직급, 역할이 변경되더라도
     * 과거 소속 정보를 확인할 수 있다.
     */
    public function up(): void
    {
        Schema::create('employee_assignments', function (Blueprint $table) {

            /**
             * 인사발령 이력 고유 ID
             */
            $table->id();

            /**
             * 대상 직원
             *
             * users.id를 참조한다.
             *
             * 퇴사한 직원의 과거 인사이력도
             * 보존해야 하므로 직원 삭제를 제한한다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 발령 점포
             *
             * 점포 직원이면 해당 stores.id를 저장한다.
             *
             * 본사 직원은 특정 점포에 소속되지 않으므로
             * NULL을 허용한다.
             *
             * Laravel에서 department가 head_office인 경우
             * store_id가 NULL인지 검증한다.
             */
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 발령 부서
             *
             * 예:
             *
             * kitchen
             * hall
             * head_office
             *
             * DB enum으로 고정하지 않고 문자열로 저장하며
             * 허용 가능한 부서는 Laravel에서 검증한다.
             */
            $table->string('department');

            /**
             * 발령 직급
             *
             * positions.id를 참조한다.
             *
             * 직급이 없는 경우도 고려하여
             * NULL을 허용한다.
             */
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions')
                ->restrictOnDelete();

            /**
             * 발령 역할
             *
             * roles.id를 참조한다.
             *
             * 역할은 시스템 권한 묶음을 의미한다.
             *
             * 예:
             *
             * staff
             * kitchen_head
             * hall_manager
             * head_office_staff
             * super_admin
             *
             * 모든 직원은 역할을 가져야 하므로
             * NULL을 허용하지 않는다.
             */
            $table->foreignId('role_id')
                ->constrained('roles')
                ->restrictOnDelete();

            /**
             * 발령 시작일
             *
             * 해당 소속 및 역할이
             * 실제 적용되기 시작한 날짜이다.
             */
            $table->date('effective_from');

            /**
             * 발령 종료일
             *
             * 해당 인사발령의 적용이 끝난 날짜이다.
             *
             * 현재 적용 중인 발령은
             * 종료일이 없으므로 NULL이다.
             *
             * 새로운 발령이 적용될 때 Laravel에서
             * 기존 현재 발령의 종료일을 처리한다.
             */
            $table->date('effective_to')
                ->nullable();

            /**
             * 발령 사유 또는 메모
             *
             * 예:
             *
             * 무역점 전보
             * 주방 부서 이동
             * 헤드셰프 승진
             * 본사 발령
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('note')
                ->nullable();

            /**
             * 발령 등록자
             *
             * 해당 인사발령을 등록한
             * users.id를 저장한다.
             *
             * 인사 이력의 책임 추적을 위해 보존한다.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 발령 정보가 수정된 경우
             * 마지막 수정 사용자를 저장한다.
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
             * 같은 직원에게 동일한 시작일의
             * 인사발령이 중복 생성되는 것을 방지한다.
             */
            $table->unique([
                'user_id',
                'effective_from',
            ]);

            /**
             * 직원별 인사발령 이력 조회용 인덱스
             */
            $table->index([
                'user_id',
                'effective_from',
                'effective_to',
            ]);

            /**
             * 점포 및 부서별 과거 소속 직원
             * 조회를 위한 인덱스
             */
            $table->index([
                'store_id',
                'department',
                'effective_from',
            ]);
        });
    }

    /**
     * 직원 인사발령 이력 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_assignments');
    }
};