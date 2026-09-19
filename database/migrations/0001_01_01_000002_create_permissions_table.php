<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 권한 테이블 생성
     *
     * Till White 시스템에서 사용되는 개별 기능 권한을 관리한다.
     *
     * 사용자의 역할(Role)에 기능 권한을 직접 하드코딩하지 않고
     * 각각의 권한을 독립적으로 관리하기 위한 테이블이다.
     *
     * 향후 생산, 폐기뿐만 아니라 스케줄, 레시피, 매출,
     * 재고, 발주 등의 기능이 추가되더라도 새로운 권한을
     * 추가하는 방식으로 시스템을 확장할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {

            /**
             * 권한 고유 ID
             *
             * DB 내부에서 권한을 구분하기 위한 기본키(PK)이다.
             *
             * role_permissions 테이블에서 permission_id를 통해
             * 역할과 권한을 연결할 때 사용한다.
             */
            $table->id();

            /**
             * 권한 코드
             *
             * 프로그램 내부에서 특정 권한을 식별하기 위한
             * 변경되지 않는 고유 코드이다.
             *
             * "기능.행동" 형식으로 통일하여
             * 권한의 목적을 쉽게 파악할 수 있도록 한다.
             *
             * 예)
             * production.view         = 생산 기록 조회
             * production.create       = 생산 기록 입력
             * production.update       = 생산 기록 수정
             * production.proxy_create = 생산 기록 대리 입력
             * production.proxy_update = 생산 기록 대리 수정
             *
             * waste.view              = 폐기 기록 조회
             * waste.create            = 폐기 기록 입력
             * waste.update            = 폐기 기록 수정
             * waste.proxy_create      = 폐기 기록 대리 입력
             * waste.proxy_update      = 폐기 기록 대리 수정
             *
             * schedule.view           = 근무 스케줄 조회
             * schedule.manage         = 근무 스케줄 등록 및 수정
             *
             * 향후 확장 예)
             * recipe.view
             * recipe.manage
             * sales.view
             * sales.manage
             * inventory.view
             * inventory.manage
             * order.view
             * order.manage
             *
             * 동일한 권한 코드가 중복 등록되면 안 되므로
             * unique 제약조건을 사용한다.
             */
            $table->string('code')->unique();

            /**
             * 권한 표시명
             *
             * 관리자 화면이나 권한 설정 화면에서
             * 사람이 이해하기 쉽게 보여줄 권한 이름이다.
             *
             * 예)
             * 생산 기록 조회
             * 생산 기록 대리 입력
             * 폐기 기록 수정
             * 스케줄 관리
             *
             * 실제 프로그램의 권한 판단에는 code를 사용하고
             * name은 화면 표시 목적으로 사용한다.
             */
            $table->string('name');

            /**
             * 권한 설명
             *
             * 해당 권한으로 정확히 어떤 작업을 할 수 있는지
             * 상세하게 설명하기 위한 값이다.
             *
             * 예)
             * "담당 점포의 생산 기록을 조회할 수 있다."
             * "담당 부서 근무자의 생산 기록을 대신 입력할 수 있다."
             *
             * 향후 권한의 종류가 많아졌을 때 이름만으로 발생할 수 있는
             * 혼동을 방지하기 위해 별도의 설명을 저장한다.
             *
             * 반드시 필요한 값은 아니므로 nullable로 관리한다.
             */
            $table->text('description')->nullable();

            /**
             * 권한 사용 여부
             *
             * true(1)  = 현재 사용중인 권한
             * false(0) = 현재 사용하지 않는 권한
             *
             * 기능이 폐지되거나 일시적으로 사용되지 않더라도
             * 기존 역할과의 권한 연결 및 과거 설정을 보존하기 위해
             * 데이터를 바로 삭제하지 않고 활성 상태로 관리한다.
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
     * 권한 테이블 삭제
     *
     * Migration을 rollback할 때 permissions 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};