<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 역할별 권한 연결 테이블 생성
     *
     * roles 테이블의 역할과 permissions 테이블의 권한을
     * 서로 연결하기 위한 중간 테이블이다.
     *
     * 하나의 역할은 여러 개의 권한을 가질 수 있고,
     * 하나의 권한 역시 여러 역할에 부여될 수 있으므로
     * 다대다(Many-to-Many) 관계로 관리한다.
     *
     * 예)
     * kitchen_head → production.view
     * kitchen_head → production.proxy_create
     * kitchen_head → waste.proxy_create
     * hall_manager → schedule.manage
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {

            /**
             * 역할 고유 ID
             *
             * roles 테이블의 id를 참조한다.
             *
             * 해당 권한이 어떤 역할에 부여되어 있는지를
             * 확인하기 위해 사용한다.
             */
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            /**
             * 권한 고유 ID
             *
             * permissions 테이블의 id를 참조한다.
             *
             * 해당 역할에 어떤 기능 권한이 부여되어 있는지를
             * 확인하기 위해 사용한다.
             */
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();

            /**
             * 역할과 권한 조합을 기본키(PK)로 사용한다.
             *
             * 동일한 역할에 동일한 권한이 두 번 이상
             * 중복 등록되는 것을 DB 단계에서 방지한다.
             *
             * 예)
             * role_id = 2, permission_id = 5 조합은
             * 테이블에 한 번만 존재할 수 있다.
             *
             * 단순 연결 테이블이므로 별도의 id 컬럼은 만들지 않는다.
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