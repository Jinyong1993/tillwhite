<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품 테이블 생성
     *
     * 각 점포에서 생산하거나 판매하는
     * 제품의 기본 정보를 관리한다.
     *
     * 제품은 점포별로 독립적으로 관리하며,
     * 하나의 제품은 하나의 카테고리에 속한다.
     *
     * 생산 담당 부서와 관리 담당 부서는
     * 서로 다르게 지정할 수 있다.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            /**
             * 제품 고유 ID
             */
            $table->id();

            /**
             * 제품이 속한 점포
             *
             * stores.id를 참조한다.
             *
             * 과거 생산·폐기·로스 기록을 보존해야 하므로
             * 점포 삭제 시 제품이 자동 삭제되지 않도록 한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 제품 카테고리
             *
             * product_categories.id를 참조한다.
             *
             * 제품이 연결되어 있는 카테고리는
             * 삭제할 수 없도록 제한한다.
             */
            $table->foreignId('product_category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();

            /**
             * 제품명
             *
             * 실제 제품 관리 및 생산·폐기 관리 화면에
             * 표시되는 제품 이름이다.
             *
             * 예:
             *
             * 우유식빵
             * 밤식빵
             * 레드벨벳케이크
             * 바닐라 휘낭시에
             */
            $table->string('name');

            /**
             * 제품 생산 담당 부서
             *
             * 실제 제품을 생산하는 부서를 저장한다.
             *
             * 예:
             *
             * kitchen
             * → 주방
             *
             * hall
             * → 홀
             *
             * 허용 가능한 값은
             * Laravel에서 검증한다.
             */
            $table->string('production_department');

            /**
             * 제품 관리 담당 부서
             *
             * 제품의 진열, 판매 또는 관리를
             * 담당하는 부서를 저장한다.
             *
             * 생산 담당 부서와 동일할 수도 있고
             * 서로 다를 수도 있다.
             *
             * 예:
             *
             * 레드벨벳케이크
             * production_department = kitchen
             * management_department = hall
             */
            $table->string('management_department');

            /**
             * 화면 표시 순서
             *
             * 같은 카테고리 안에서
             * 제품을 표시할 순서를 지정한다.
             *
             * 숫자가 작은 제품을 먼저 표시한다.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            /**
             * 제품 사용 여부
             *
             * 더 이상 생산하거나 판매하지 않는 제품은
             * 과거 기록 보존을 위해 삭제하지 않고
             * 비활성화할 수 있다.
             *
             * true:
             * 현재 사용 중
             *
             * false:
             * 판매 또는 생산 종료
             */
            $table->boolean('is_active')
                ->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 같은 점포에서는 동일한 제품명을
             * 중복 등록하지 못하도록 한다.
             *
             * 다른 점포에서는 같은 제품명을
             * 사용할 수 있다.
             *
             * 예:
             *
             * 무역점 + 우유식빵 → 가능
             * 무역점 + 우유식빵 → 중복 불가
             * 더현대서울점 + 우유식빵 → 가능
             */
            $table->unique([
                'store_id',
                'name',
            ]);

            /**
             * 점포 및 카테고리별 제품 조회용 인덱스
             *
             * 생산·폐기 관리 화면에서
             * 점포의 카테고리별 활성 제품을
             * 조회할 때 사용한다.
             */
            $table->index([
                'store_id',
                'product_category_id',
                'is_active',
                'sort_order',
            ]);

            /**
             * 점포 및 생산 부서별 제품 조회용 인덱스
             *
             * 주방 또는 홀 직원에게
             * 해당 부서에서 생산하는 제품을 조회할 때 사용한다.
             */
            $table->index([
                'store_id',
                'production_department',
                'is_active',
            ]);

            /**
             * 점포 및 관리 부서별 제품 조회용 인덱스
             */
            $table->index([
                'store_id',
                'management_department',
                'is_active',
            ]);
        });
    }

    /**
     * 제품 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};