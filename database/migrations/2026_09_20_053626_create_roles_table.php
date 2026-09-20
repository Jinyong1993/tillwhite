<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 시스템 역할 테이블 생성
     *
     * Till White 사용자의 시스템상 역할을 관리한다.
     *
     * role은 회사에서 사용하는 직급/직책(position)과는
     * 별개의 개념이다.
     *
     * position
     * → 직원의 회사 내 직급 또는 직책
     *
     * role
     * → 시스템에서 사용자에게 부여되는 권한 묶음
     *
     * 실제 기능별 권한은 permissions 테이블에서 관리하고,
     * role_permissions를 통해 역할과 권한을 연결한다.
     *
     * 따라서 Laravel에서는 가능한 한 역할 이름 자체보다
     * 사용자에게 부여된 permission을 기준으로
     * 기능 접근 여부를 판단할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            /**
             * 역할 고유 ID
             *
             * DB 내부에서 역할을 구분하기 위한 기본키(PK)이다.
             *
             * users 테이블의 role_id와
             * role_permissions 테이블에서 참조한다.
             */
            $table->id();

            /**
             * 역할 고유 코드
             *
             * 프로그램 내부에서 역할을 안정적으로
             * 식별하기 위해 사용하는 코드이다.
             *
             * 화면에 표시되는 역할명이 변경되더라도
             * 내부 코드는 유지할 수 있다.
             *
             * 동일한 역할 코드가 중복되지 않도록
             * unique로 관리한다.
             *
             * 초기 예:
             * staff
             * kitchen_head
             * hall_manager
             * head_office_staff
             * head_office_manager
             */
            $table->string('code')->unique();

            /**
             * 역할명
             *
             * 시스템 및 직원관리 화면에서
             * 사용자에게 표시되는 역할 이름이다.
             *
             * 초기 예:
             * 일반 직원
             * 헤드셰프
             * 홀 매니저
             * 본사 직원
             * 본사 관리자
             *
             * 동일한 역할명이 중복 등록되지 않도록
             * unique로 관리한다.
             */
            $table->string('name')->unique();

            /**
             * 역할 설명
             *
             * 해당 역할이 어떤 목적으로 사용되는지
             * 시스템 관리자가 확인할 수 있도록 설명을 저장한다.
             *
             * 역할명만으로 충분한 경우에는 설명이 없어도 되므로
             * nullable로 관리한다.
             *
             * 실제 권한 판단은 이 설명을 사용하지 않고
             * permissions와 role_permissions를 통해 처리한다.
             */
            $table->text('description')->nullable();

            /**
             * 역할 사용 여부
             *
             * true(1)  = 현재 사용중
             * false(0) = 현재 사용하지 않음
             *
             * 기존 직원에게 사용됐던 역할을 물리적으로 삭제하지 않고
             * 신규 사용자에게 더 이상 부여하지 못하도록
             * 비활성화할 수 있게 한다.
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
     * 시스템 역할 테이블 삭제
     *
     * Migration을 rollback할 때 roles 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};