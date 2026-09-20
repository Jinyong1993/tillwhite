<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 역할별 권한 연결 테이블 생성
     *
     * roles와 permissions 사이의 다대다(N:M) 관계를 관리한다.
     *
     * 하나의 역할은 여러 개의 권한을 가질 수 있고,
     * 하나의 권한 역시 여러 역할에 부여될 수 있다.
     *
     * 예:
     *
     * 헤드셰프
     * → production.view
     * → production.create
     * → schedule.view
     * → schedule.manage
     * → product.view
     * → product.manage
     *
     * 본사 직원
     * → production.view
     * → waste.view
     * → product.view
     * → sales.view
     * → employee.view
     *
     * 실제 데이터 접근 범위는 권한 존재 여부뿐만 아니라
     * 사용자의 점포, 부서 및 역할 등을 함께 확인하여
     * Laravel에서 최종적으로 제한한다.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            /**
             * 역할 ID
             *
             * roles 테이블의 id를 참조한다.
             *
             * 역할이 삭제되는 경우 해당 역할과 권한 사이의
             * 연결 정보는 더 이상 의미가 없으므로
             * 연결 데이터는 함께 삭제되도록 cascadeOnDelete를 사용한다.
             */
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            /**
             * 권한 ID
             *
             * permissions 테이블의 id를 참조한다.
             *
             * 권한이 삭제되는 경우 해당 권한과 역할 사이의
             * 연결 정보도 함께 삭제되도록 cascadeOnDelete를 사용한다.
             */
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();

            /**
             * 복합 기본키
             *
             * 동일한 역할에 동일한 권한이
             * 두 번 이상 연결되는 것을 DB 단계에서 방지한다.
             *
             * 이 테이블은 역할과 권한을 연결하는 용도로만 사용하므로
             * 별도의 id 컬럼을 만들지 않고
             * role_id + permission_id 조합을 기본키로 사용한다.
             */
            $table->primary([
                'role_id',
                'permission_id',
            ]);
        });
    }

    /**
     * 역할별 권한 연결 테이블 삭제
     *
     * Migration을 rollback할 때
     * role_permissions 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};