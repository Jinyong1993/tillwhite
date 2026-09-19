<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 역할 테이블 생성
     *
     * 사용자의 시스템상 역할(Role)을 관리한다.
     *
     * 역할 자체에 기능 권한을 직접 하드코딩하지 않고,
     * 추후 permissions 및 role_permissions 테이블과 연결하여
     * 역할별로 사용할 수 있는 기능을 관리할 수 있도록 한다.
     *
     * 이를 통해 생산, 폐기뿐만 아니라 향후 스케줄, 레시피,
     * 매출, 재고, 발주 등의 기능이 추가되더라도
     * 기존 사용자 테이블 구조를 변경하지 않고 권한을 확장할 수 있다.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {

            /**
             * 역할 고유 ID
             *
             * DB 내부에서 역할을 구분하기 위한 기본키(PK)이다.
             *
             * users 테이블에서는 role_id를 통해 이 값을 참조하여
             * 사용자가 어떤 역할을 가지고 있는지 연결한다.
             */
            $table->id();

            /**
             * 역할 코드
             *
             * 프로그램 내부에서 역할을 식별하기 위한 고유한 값이다.
             *
             * 예)
             * staff              = 일반 직원
             * kitchen_head       = 주방 헤드 셰프
             * hall_manager       = 홀 매니저
             * operations_staff   = 운영진 일반 직원
             * operations_manager = 운영진 매니저
             *
             * 화면에 표시되는 이름이 변경되더라도 프로그램 내부에서는
             * code를 기준으로 역할을 안정적으로 식별할 수 있도록 한다.
             *
             * 동일한 역할 코드가 중복 등록되면 안 되므로
             * unique 제약조건을 사용한다.
             */
            $table->string('code')->unique();

            /**
             * 역할 표시명
             *
             * 사용자 화면에서 보여줄 역할 이름이다.
             *
             * 예)
             * 일반 직원
             * 헤드 셰프
             * 홀 매니저
             * 운영진
             * 운영 매니저
             *
             * 시스템 내부 판단에는 code를 사용하고,
             * name은 사람이 읽기 위한 표시값으로 사용한다.
             */
            $table->string('name');

            /**
             * 역할 설명
             *
             * 해당 역할이 어떤 목적으로 사용되는지 설명한다.
             *
             * 관리자 화면에서 역할을 확인하거나
             * 향후 역할 종류가 많아졌을 때 각각의 용도를
             * 쉽게 파악할 수 있도록 별도의 설명을 저장한다.
             *
             * 반드시 필요한 값은 아니므로 nullable로 관리한다.
             */
            $table->text('description')->nullable();

            /**
             * 역할 사용 여부
             *
             * true(1)  = 현재 사용할 수 있는 역할
             * false(0) = 현재 사용하지 않는 역할
             *
             * 기존 사용자의 과거 역할 및 관련 기록을 보존하기 위해
             * 사용하지 않는 역할이라고 해서 바로 삭제하지 않고
             * 활성 상태를 통해 관리한다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 역할이 시스템에 등록된 시간
             * updated_at = 역할 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();
        });
    }

    /**
     * 역할 테이블 삭제
     *
     * Migration을 rollback할 때 roles 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};