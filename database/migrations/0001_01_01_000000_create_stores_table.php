<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 점포 테이블 생성
     *
     * Till White 시스템에서 사용하는 모든 점포의 기본 정보를 관리한다.
     *
     * 향후 생산, 폐기, 스케줄, 매출, 재고, 발주 등의 데이터는
     * store_id를 통해 이 테이블의 점포와 연결할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {

            /**
             * 점포 고유 ID
             *
             * DB 내부에서 점포를 구분하기 위한 기본키(PK)이다.
             *
             * users, production_records, waste_records 등
             * 다른 테이블에서는 store_id를 통해 이 값을 참조한다.
             */
            $table->id();

            /**
             * 점포 코드
             *
             * 실제 업무에서 점포를 구분하기 위한 고유 코드이다.
             *
             * 예)
             * 1 = 무역점
             * 2 = 다른 점포
             *
             * id는 DB 내부 관계를 위한 값이고,
             * store_code는 업무상 점포를 식별하기 위한 값이므로
             * 서로 별도로 관리한다.
             *
             * 동일한 점포 코드가 중복 등록되면 안 되므로
             * unique 제약조건을 사용한다.
             */
            $table->string('store_code')->unique();

            /**
             * 점포명
             *
             * 사용자 화면에 표시되는 실제 점포 이름이다.
             *
             * 예)
             * 무역점
             * 삼성점
             */
            $table->string('name');

            /**
             * 점포 운영 상태
             *
             * true(1)  = 현재 영업중
             * false(0) = 폐점
             *
             * 점포가 폐점하더라도 기존 생산량, 폐기량, 매출 등의
             * 과거 기록은 보존되어야 한다.
             *
             * 따라서 폐점했다고 점포 데이터를 삭제하지 않고
             * status를 false로 변경하여 운영 상태를 관리한다.
             */
            $table->boolean('status')->default(true);

            /**
             * 점포 영업 시작일
             *
             * 해당 점포가 실제 영업을 시작한 날짜이다.
             *
             * 기존 점포처럼 정확한 오픈일을 알 수 없는 경우도
             * 있을 수 있으므로 nullable로 관리한다.
             *
             * 시간까지 관리할 필요는 없으므로
             * timestamp가 아닌 date 타입을 사용한다.
             */
            $table->date('opened_at')->nullable();

            /**
             * 점포 폐점일
             *
             * 해당 점포가 실제로 폐점한 날짜이다.
             *
             * 영업중인 경우:
             * status = true
             * closed_at = null
             *
             * 폐점한 경우:
             * status = false
             * closed_at = 실제 폐점일
             *
             * 시간까지 관리할 필요는 없으므로
             * timestamp가 아닌 date 타입을 사용한다.
             */
            $table->date('closed_at')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 시스템에 점포가 등록된 시간
             * updated_at = 점포 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();

            /**
             * 시스템상 삭제 일시 (Soft Delete)
             *
             * deleted_at = null
             * → 정상적으로 사용하는 데이터
             *
             * deleted_at에 값이 존재
             * → 시스템에서 삭제 처리된 데이터
             *
             * closed_at과 deleted_at은 의미가 다르다.
             *
             * closed_at
             * → 실제 점포가 폐점한 날짜
             *
             * deleted_at
             * → 시스템에서 해당 점포 데이터를 삭제 처리한 시간
             *
             * 점포와 연결된 과거 생산, 폐기 등의 기록을 보호하기 위해
             * 실제 DB 행을 바로 삭제하지 않고 Soft Delete를 사용한다.
             */
            $table->softDeletes();
        });
    }

    /**
     * 점포 테이블 삭제
     *
     * Migration을 rollback할 때 stores 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};