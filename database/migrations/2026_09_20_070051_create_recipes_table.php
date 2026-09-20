<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 레시피 테이블 생성
     *
     * 각 점포에서 사용하는 제품별 레시피의
     * 기본 정보를 관리한다.
     *
     * 주방의 제빵 레시피뿐만 아니라
     * 홀의 커피, 음료, 디저트 등의 레시피도
     * 동일한 구조로 관리할 수 있다.
     *
     * 실제 재료, 제조 순서, 참고 이미지는
     * 각각 별도의 하위 테이블에서 관리한다.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {

            /**
             * 레시피 고유 ID
             */
            $table->id();

            /**
             * 레시피가 속한 점포
             *
             * stores.id를 참조한다.
             *
             * 레시피의 접근 범위를 결정하는
             * 중요한 기준값이다.
             *
             * 점포 직원과 점포 관리자는
             * 자신의 store_id와 일치하는
             * 레시피만 접근할 수 있다.
             *
             * 본사 직원 또는 최고관리자가 접근하더라도
             * 레시피 자체의 점포 소속은 변경하지 않는다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 레시피 대상 제품
             *
             * products.id를 참조한다.
             *
             * 해당 제품을 만드는 방법과
             * 재료 정보를 연결하기 위한 값이다.
             *
             * Laravel에서 제품의 store_id와
             * 레시피의 store_id가 동일한지 검증한다.
             */
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            /**
             * 레시피 담당 부서
             *
             * 레시피를 실제로 사용하는
             * 점포 내 부서를 저장한다.
             *
             * 예:
             *
             * kitchen
             * → 주방 제빵 및 생산 레시피
             *
             * hall
             * → 커피, 음료 등 홀 레시피
             *
             * 허용 가능한 값은 Laravel에서 검증한다.
             */
            $table->string('department');

            /**
             * 레시피 이름
             *
             * 화면에서 표시할 레시피 이름이다.
             *
             * 일반적으로 제품명과 동일하게 사용할 수 있지만
             * 제품명과 별도로 관리하여 향후 다양한 레시피
             * 표현이 가능하도록 한다.
             *
             * 예:
             *
             * 우유식빵 기본 레시피
             * 아이스 카페라떼
             * 시즌용 레드벨벳케이크
             */
            $table->string('name');

            /**
             * 레시피 설명
             *
             * 레시피 전체에 대한 설명이나
             * 작업 시 주의사항 등을 저장한다.
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('description')->nullable();

            /**
             * 레시피 사용 여부
             *
             * 더 이상 사용하지 않는 레시피를
             * 과거 기록 보존을 위해 삭제하지 않고
             * 비활성화할 수 있다.
             *
             * true:
             * 현재 사용하는 레시피
             *
             * false:
             * 현재 사용하지 않는 레시피
             */
            $table->boolean('is_active')
                ->default(true);

            /**
             * 레시피 최초 등록자
             *
             * 레시피를 최초로 등록한 직원의
             * users.id를 저장한다.
             *
             * 등록자가 퇴사하더라도
             * 과거 등록 이력을 유지한다.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 레시피 마지막 수정자
             *
             * 마지막으로 레시피를 수정한 직원의
             * users.id를 저장한다.
             *
             * 아직 수정되지 않은 경우 NULL이다.
             */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 동일 점포의 동일 제품에
             * 같은 이름의 레시피가 중복 생성되는 것을 방지한다.
             *
             * 하나의 제품에 여러 레시피를 만드는 것은 허용한다.
             *
             * 예:
             *
             * 우유식빵 + 기본 레시피
             * 우유식빵 + 테스트 레시피
             *
             * → 가능
             */
            $table->unique([
                'store_id',
                'product_id',
                'name',
            ]);

            /**
             * 점포 및 부서별 레시피 조회용 인덱스
             *
             * 점포 직원이 자신의 점포와 부서에 해당하는
             * 레시피를 조회할 때 사용한다.
             */
            $table->index([
                'store_id',
                'department',
                'is_active',
            ]);

            /**
             * 제품별 레시피 조회용 인덱스
             */
            $table->index([
                'product_id',
                'is_active',
            ]);
        });
    }

    /**
     * 레시피 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};