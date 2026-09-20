<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 직원별 근무 자동 배정 규칙 테이블 생성
     *
     * 월간 근무표 자동 생성 시
     * 직원별로 어떤 근무코드를 어느 정도 비율로
     * 배정할지 설정한다.
     *
     * 예:
     *
     * 홍길동
     * A → 70
     * B → 20
     * C → 10
     *
     * 김철수
     * A → 10
     * B → 20
     * C → 70
     *
     * 여기서 weight는 정확한 확률을 저장하는 값이 아니라
     * 자동 생성 시 사용하는 상대적인 가중치이다.
     *
     * 실제 근무표는 work_schedules에 생성되며,
     * 이 테이블은 자동 생성 시 참고하는 설정값이다.
     */
    public function up(): void
    {
        Schema::create('work_schedule_rules', function (Blueprint $table) {

            /**
             * 규칙 고유 ID
             *
             * 하나의 직원별 근무코드 규칙을
             * 식별하기 위한 기본키이다.
             */
            $table->id();

            /**
             * 대상 직원
             *
             * 해당 자동 배정 규칙을 적용할 직원이다.
             *
             * users 테이블의 id를 참조한다.
             *
             * 직원이 삭제되면 해당 직원의 자동 배정 규칙도
             * 더 이상 의미가 없으므로 함께 삭제한다.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /**
             * 근무코드
             *
             * 해당 직원에게 자동 배정할 수 있는
             * 근무코드를 지정한다.
             *
             * 예:
             * A
             * B
             * C
             *
             * work_codes 테이블의 id를 참조한다.
             */
            $table->foreignId('work_code_id')
                ->constrained('work_codes')
                ->cascadeOnDelete();

            /**
             * 자동 배정 가중치
             *
             * 해당 근무코드가 자동 생성 시
             * 얼마나 자주 배정될지를 결정하는 상대적인 값이다.
             *
             * 예:
     *
             * A = 70
             * B = 20
             * C = 10
             *
             * 값이 클수록 해당 근무코드가
             * 상대적으로 더 많이 배정된다.
             *
             * 정확한 100% 합계를 DB에서 강제하지 않는다.
             * 자동 생성 로직에서 전체 가중치를 기준으로 계산한다.
             */
            $table->unsignedInteger('weight')->default(0);

            /**
             * 규칙 사용 여부
             *
             * true(1)
             * → 자동 생성 시 해당 규칙 사용
             *
             * false(0)
             * → 규칙을 저장해두되 자동 생성에서는 사용하지 않음
             *
             * 기존 설정을 삭제하지 않고 일시적으로
             * 사용하지 않을 수 있도록 한다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 직원 + 근무코드 중복 방지
             *
             * 한 직원에게 동일한 근무코드를
             * 여러 번 등록할 필요가 없으므로
             * 하나의 조합은 한 번만 존재하도록 한다.
             */
            $table->unique(
                ['user_id', 'work_code_id'],
                'work_schedule_rules_user_work_code_unique'
            );

            /**
             * 직원별 자동 배정 규칙 조회용 인덱스
             */
            $table->index([
                'user_id',
                'is_active',
            ]);
        });
    }

    /**
     * 직원별 근무 자동 배정 규칙 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_rules');
    }
};