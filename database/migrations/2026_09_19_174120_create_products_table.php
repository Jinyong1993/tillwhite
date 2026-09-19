<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품 테이블 생성
     *
     * Till White에서 생산 및 폐기 관리의 기준이 되는
     * 제품의 기본 정보를 관리한다.
     *
     * 생산 기록과 폐기 기록에서는 제품명을 직접 저장하지 않고
     * product_id를 통해 이 테이블의 제품과 연결한다.
     *
     * 향후 레시피, 매출, 재고, 발주 등의 기능에서도
     * 동일한 제품 정보를 사용할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            /**
             * 제품 고유 ID
             *
             * DB 내부에서 제품을 구분하기 위한 기본키(PK)이다.
             *
             * production_records, waste_records 등의 테이블에서는
             * product_id를 통해 이 값을 참조한다.
             */
            $table->id();

            /**
             * 제품 코드
             *
             * 시스템 및 실제 업무에서 제품을 식별하기 위한
             * 고유한 코드이다.
             *
             * 제품명이 변경되더라도 기존 기록과 제품을
             * 안정적으로 연결할 수 있도록 제품명과 별도로 관리한다.
             *
             * 동일한 제품 코드가 중복 등록되면 안 되므로
             * unique 제약조건을 사용한다.
             */
            $table->string('product_code')->unique();

            /**
             * 제품명
             *
             * 사용자 화면에 표시되는 실제 제품 이름이다.
             *
             * 예)
             * 소금빵
             * 크루아상
             * 식빵
             *
             * 제품명이 변경되더라도 product_id는 유지되므로
             * 기존 생산 및 폐기 기록과의 관계는 유지된다.
             */
            $table->string('name');

            /**
             * 제품 카테고리
             *
             * 제품을 종류별로 구분하기 위한 값이다.
             *
             * 예)
             * 식빵
             * 조리빵
             * 페이스트리
             * 구움과자
             *
             * 초기에는 단순 문자열로 관리하고,
             * 향후 카테고리를 별도로 관리해야 할 필요가 생기면
             * categories 테이블로 분리할 수 있도록 한다.
             *
             * 카테고리가 없는 제품도 등록할 수 있도록
             * nullable로 관리한다.
             */
            $table->string('category')->nullable();

            /**
             * 수량 단위
             *
             * 생산량 및 폐기량을 어떤 단위로 관리하는지 나타낸다.
             *
             * 예)
             * ea = 개
             *
             * 현재는 완제품 생산 및 폐기 관리가 중심이므로
             * 기본값을 ea로 설정한다.
             *
             * 향후 중량이나 다른 단위를 사용하는 품목이 추가되더라도
             * 제품별 단위를 구분할 수 있도록 별도 컬럼으로 관리한다.
             */
            $table->string('unit')->default('ea');

            /**
             * 제품 사용 여부
             *
             * true(1)  = 현재 사용하는 제품
             * false(0) = 현재 사용하지 않는 제품
             *
             * 단종된 제품이라도 과거 생산량 및 폐기량 기록은
             * 계속 보존되어야 한다.
             *
             * 따라서 제품을 단종했다고 바로 삭제하지 않고
             * is_active를 false로 변경하여 관리한다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 제품이 시스템에 등록된 시간
             * updated_at = 제품 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();

            /**
             * 시스템상 삭제 일시 (Soft Delete)
             *
             * deleted_at = null
             * → 정상적으로 존재하는 제품
             *
             * deleted_at에 값이 존재
             * → 시스템에서 삭제 처리된 제품
             *
             * is_active와 deleted_at은 서로 다른 개념이다.
             *
             * is_active = false
             * → 단종 등으로 현재 업무에서 사용하지 않는 제품
             *
             * deleted_at에 값 존재
             * → 시스템상 삭제 처리된 제품
             *
             * 기존 생산 및 폐기 기록과의 관계를 보호하기 위해
             * 실제 DB 행을 바로 삭제하지 않고 Soft Delete를 사용한다.
             */
            $table->softDeletes();
        });
    }

    /**
     * 제품 테이블 삭제
     *
     * Migration을 rollback할 때
     * products 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};