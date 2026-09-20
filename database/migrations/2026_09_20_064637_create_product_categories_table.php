<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 제품 카테고리 테이블 생성
     *
     * 각 점포에서 판매하거나 생산하는 제품을
     * 분류하기 위한 카테고리를 관리한다.
     *
     * 예:
     *
     * 브레드
     * 푀이테샌드
     * 바이츠 - 러스크
     * 케이크 - 디저트
     * 시즈널
     * 식빵류
     * 디저트류
     *
     * 카테고리는 점포별로 독립적으로 관리한다.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {

            /**
             * 제품 카테고리 고유 ID
             */
            $table->id();

            /**
             * 카테고리가 속한 점포
             *
             * stores.id를 참조한다.
             *
             * 제품 및 과거 생산·폐기 기록의 기준이 될 수 있으므로
             * 점포 삭제 시 카테고리가 자동 삭제되지 않도록 한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 카테고리 이름
             *
             * 실제 화면에 표시되는 이름이다.
             *
             * 예:
             *
             * 브레드
             * 식빵류
             * 디저트류
             */
            $table->string('name');

            /**
             * 화면 표시 순서
             *
             * 숫자가 작은 카테고리를 먼저 표시한다.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            /**
             * 카테고리 사용 여부
             *
             * 더 이상 사용하지 않는 카테고리는
             * 삭제하지 않고 비활성화할 수 있다.
             *
             * 과거 제품 및 생산 기록과의 연결을
             * 유지하기 위한 용도이다.
             */
            $table->boolean('is_active')
                ->default(true);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 같은 점포 안에서는
             * 동일한 이름의 카테고리를 중복 등록할 수 없다.
             *
             * 다른 점포에서는 같은 카테고리명을 사용할 수 있다.
             *
             * 예:
             *
             * 무역점 + 브레드 → 가능
             * 무역점 + 브레드 → 중복 불가
             * 더현대서울점 + 브레드 → 가능
             */
            $table->unique([
                'store_id',
                'name',
            ]);

            /**
             * 점포별 활성 카테고리 조회용 인덱스
             *
             * 제품 등록 및 제품 조회 화면에서
             * 해당 점포에서 현재 사용하는 카테고리를
             * 빠르게 조회할 수 있도록 한다.
             */
            $table->index([
                'store_id',
                'is_active',
                'sort_order',
            ]);
        });
    }

    /**
     * 제품 카테고리 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};