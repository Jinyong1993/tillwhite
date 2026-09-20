<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 근무코드 테이블 생성
     *
     * 직원의 근무표를 작성할 때 사용하는
     * 점포 및 부서별 근무코드를 관리한다.
     *
     * 예:
     * A = 07:30 ~ 18:00
     * B = 09:00 ~ 19:30
     * C = 10:30 ~ 21:00
     *
     * 같은 A 코드라도 점포 또는 부서가 다르면
     * 서로 다른 근무시간을 사용할 수 있도록 한다.
     *
     * 주방 근무코드는 해당 점포의 헤드셰프,
     * 홀 근무코드는 해당 점포의 홀 매니저가
     * 관리할 수 있도록 Laravel에서 권한과 범위를 제한한다.
     */
    public function up(): void
    {
        Schema::create('work_codes', function (Blueprint $table) {
            /**
             * 근무코드 고유 ID
             *
             * DB 내부에서 하나의 근무코드를
             * 식별하기 위한 기본키(PK)이다.
             */
            $table->id();

            /**
             * 근무코드가 사용되는 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 근무코드는 점포별로 관리하므로
             * 반드시 점포가 존재해야 한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 근무코드가 사용되는 부서
             *
             * 현재 사용 값:
             *
             * kitchen
             * → 주방
             *
             * hall
             * → 홀
             *
             * 본사 직원의 근무코드는 현재 범위에서 제외하고,
             * 점포의 주방/홀 근무코드를 우선 관리한다.
             *
             * 허용되는 값은 Laravel Validation에서 관리한다.
             */
            $table->string('department');

            /**
             * 근무코드
             *
             * 실제 근무표에서 사용하는 짧은 코드이다.
             *
             * 예:
             * A
             * B
             * C
             *
             * 아래의 복합 UNIQUE 제약조건을 통해
             * 같은 점포 + 같은 부서 안에서는
             * 동일한 근무코드를 중복 등록할 수 없다.
             */
            $table->string('code');

            /**
             * 근무코드 이름
             *
             * 코드 외에 사람이 이해하기 쉬운 이름이
             * 필요한 경우 사용할 수 있다.
             *
             * 예:
             * A / 오픈
             * B / 미들
             * C / 마감
             *
             * 코드만 사용하는 경우도 있으므로
             * nullable로 관리한다.
             */
            $table->string('name')->nullable();

            /**
             * 예정 출근시간
             *
             * 해당 근무코드를 사용하는 직원의
             * 기본 출근시간을 저장한다.
             *
             * 예:
             * A → 07:30
             */
            $table->time('start_time');

            /**
             * 예정 퇴근시간
             *
             * 해당 근무코드를 사용하는 직원의
             * 기본 퇴근시간을 저장한다.
             *
             * 예:
             * A → 18:00
             */
            $table->time('end_time');

            /**
             * 기본 휴게시간
             *
             * 해당 근무코드의 기본 휴게시간을
             * 분 단위로 저장한다.
             *
             * 예:
             * 60 = 1시간
             * 90 = 1시간 30분
             *
             * 휴게시간이 없는 경우에는 NULL 대신
             * 0으로 처리하여 근무시간 계산을 단순하게 한다.
             */
            $table->unsignedInteger('break_minutes')->default(0);

            /**
             * 근무코드 특이사항
             *
             * 해당 근무코드에 대한 추가 설명이나
             * 주의사항을 저장한다.
             *
             * 예:
             * 오픈 준비 담당
             * 마감 정리 포함
             *
             * 특이사항이 없을 수 있으므로 nullable로 관리한다.
             */
            $table->text('note')->nullable();

            /**
             * 표시 순서
             *
             * 근무코드 선택 화면에서
             * A, B, C 등의 표시 순서를 관리한다.
             *
             * 별도 순서를 지정하지 않으면 0을 사용한다.
             */
            $table->unsignedInteger('sort_order')->default(0);

            /**
             * 근무코드 사용 여부
             *
             * true(1)
             * → 현재 사용중
             *
             * false(0)
             * → 더 이상 신규 근무표에서 사용하지 않음
             *
             * 이미 과거 근무표에서 사용된 근무코드는
             * 기록 보존을 위해 물리적으로 삭제하기보다
             * 비활성화하여 관리한다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 근무코드 등록 시간
             * updated_at = 근무코드 마지막 수정 시간
             */
            $table->timestamps();

            /**
             * 근무코드 중복 방지
             *
             * 같은 점포의 같은 부서에서는
             * 동일한 근무코드를 두 번 등록할 수 없다.
             *
             * 예:
             *
             * 무역점 / kitchen / A
             * 무역점 / kitchen / A
             * → 중복 불가
             *
             * 무역점 / kitchen / A
             * 무역점 / hall / A
             * → 가능
             *
             * 무역점 / kitchen / A
             * 더현대서울점 / kitchen / A
             * → 가능
             */
            $table->unique([
                'store_id',
                'department',
                'code',
            ]);
        });
    }

    /**
     * 근무코드 테이블 삭제
     *
     * Migration을 rollback할 때
     * work_codes 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_codes');
    }
};