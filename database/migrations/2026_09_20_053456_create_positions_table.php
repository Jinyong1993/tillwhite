<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 직급 / 직책 테이블 생성
     *
     * Till White 직원에게 표시되는 회사 내 직급 또는 직책을 관리한다.
     *
     * position은 직원의 조직상 직급/직책을 나타내기 위한 정보이며,
     * 시스템에서 사용할 수 있는 기능이나 권한을 직접 결정하지 않는다.
     *
     * 실제 시스템 권한은 roles 및 permissions를 통해 별도로 관리한다.
     *
     * 이를 통해 향후 회사의 직급 체계가 변경되더라도
     * 시스템 권한 구조와 독립적으로 관리할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            /**
             * 직급 / 직책 고유 ID
             *
             * DB 내부에서 직급/직책을 구분하기 위한 기본키(PK)이다.
             *
             * users 테이블의 position_id에서
             * 해당 직급/직책을 참조할 수 있다.
             */
            $table->id();

            /**
             * 직급 / 직책 고유 코드
             *
             * 프로그램 내부에서 직급/직책을 안정적으로
             * 식별하기 위해 사용하는 코드이다.
             *
             * 화면에 표시되는 이름이 변경되더라도
             * 내부 코드는 유지할 수 있다.
             *
             * 동일한 코드가 중복 등록되지 않도록 unique로 관리한다.
             *
             * 예:
             * STAFF
             * ASSISTANT_MANAGER
             * MANAGER
             * HEAD_CHEF
             */
            $table->string('code')->unique();

            /**
             * 직급 / 직책명
             *
             * 직원관리 및 사용자 정보 화면 등에
             * 실제로 표시되는 이름이다.
             *
             * 예:
             * 사원
             * 주임
             * 매니저
             * 헤드셰프
             *
             * 동일한 직급/직책이 중복 등록되는 것을 방지하기 위해
             * unique로 관리한다.
             */
            $table->string('name')->unique();

            /**
             * 직급 / 직책 표시 순서
             *
             * 직원관리 화면의 직급 선택 목록이나
             * 시스템의 직급관리 화면에서 일정한 순서로
             * 표시하기 위해 사용한다.
             *
             * 별도의 순서를 지정하지 않은 경우 0을 사용한다.
             *
             * null과 0을 별도로 처리할 필요가 없도록
             * nullable이 아닌 기본값 0으로 관리한다.
             */
            $table->unsignedInteger('sort_order')->default(0);

            /**
             * 직급 / 직책 사용 여부
             *
             * true(1)  = 현재 사용중
             * false(0) = 현재 사용하지 않음
             *
             * 기존 직원이나 과거 기록에서 사용한 직급을
             * 물리적으로 삭제하지 않고 신규 직원 등록 및 수정 화면에서
             * 선택할 수 없도록 관리하기 위해 사용한다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 직급/직책이 시스템에 등록된 시간
             * updated_at = 직급/직책 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();
        });
    }

    /**
     * 직급 / 직책 테이블 삭제
     *
     * Migration을 rollback할 때 positions 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};