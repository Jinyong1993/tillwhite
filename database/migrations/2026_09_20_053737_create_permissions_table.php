<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 시스템 권한 테이블 생성
     *
     * Till White 시스템에서 사용자가 수행할 수 있는
     * 기능별 권한을 관리한다.
     *
     * permission은 특정 역할 자체를 의미하지 않고
     * 하나의 구체적인 기능 수행 권한을 의미한다.
     *
     * 예:
     * production.view
     * production.create
     * schedule.manage
     * product.manage
     *
     * 역할과 권한은 role_permissions 테이블을 통해 연결한다.
     *
     * 이를 통해 역할별 권한 구성이 변경되더라도
     * Laravel의 기능별 권한 검사 코드를 최대한
     * 변경하지 않고 운영할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            /**
             * 권한 고유 ID
             *
             * DB 내부에서 권한을 구분하기 위한 기본키(PK)이다.
             *
             * role_permissions 테이블에서
             * permission_id로 참조한다.
             */
            $table->id();

            /**
             * 권한 고유 코드
             *
             * Laravel에서 실제 권한을 검사할 때 사용하는
             * 시스템 내부용 고유 코드이다.
             *
             * 동일한 권한 코드가 중복 등록되지 않도록
             * unique로 관리한다.
             *
             * 예:
             * production.view
             * production.create
             * production.update
             * production.delete
             *
             * waste.view
             * waste.create
             * waste.update
             * waste.delete
             *
             * schedule.view
             * schedule.manage
             *
             * product.view
             * product.manage
             *
             * sales.view
             * sales.manage
             *
             * employee.view
             * employee.manage
             *
             * store.view
             * store.manage
             *
             * system.view
             * system.manage
             */
            $table->string('code')->unique();

            /**
             * 권한명
             *
             * 시스템의 역할 및 권한 관리 화면에서
             * 사람이 이해하기 쉬운 이름으로 표시하기 위해 사용한다.
             *
             * 예:
             * 생산 조회
             * 생산 입력
             * 근무 관리
             * 제품 관리
             * 매출 조회
             * 직원 관리
             */
            $table->string('name')->unique();

            /**
             * 권한 설명
             *
             * 해당 권한이 어떤 기능을 허용하는지
             * 시스템 관리자가 확인할 수 있도록 설명을 저장한다.
             *
             * 권한명만으로 충분한 경우에는 설명이 없어도 되므로
             * nullable로 관리한다.
             */
            $table->text('description')->nullable();

            /**
             * 권한 사용 여부
             *
             * true(1)  = 현재 사용중
             * false(0) = 현재 사용하지 않음
             *
             * 기존 역할과 연결되어 있던 권한을 물리적으로 삭제하지 않고
             * 시스템에서 더 이상 사용하지 않도록 비활성화할 수 있다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 권한이 시스템에 등록된 시간
             * updated_at = 권한 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();
        });
    }

    /**
     * 시스템 권한 테이블 삭제
     *
     * Migration을 rollback할 때 permissions 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};