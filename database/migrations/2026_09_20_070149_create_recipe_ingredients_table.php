<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 레시피 재료 테이블 생성
     *
     * 하나의 레시피에 사용되는 여러 재료와
     * 각각의 사용량 및 단위를 관리한다.
     *
     * 제빵 재료뿐만 아니라 커피, 음료, 디저트 등
     * 다양한 종류의 레시피에서 공통으로 사용할 수 있다.
     *
     * 단위는 특정 값으로 DB에 고정하지 않고
     * 문자열로 저장하여 향후 새로운 단위가 추가되어도
     * 테이블 구조를 변경하지 않고 사용할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {

            /**
             * 레시피 재료 고유 ID
             */
            $table->id();

            /**
             * 재료가 속한 레시피
             *
             * recipes.id를 참조한다.
             *
             * 재료는 특정 레시피에 종속되는 정보이므로
             * 레시피가 실제 삭제될 경우 해당 레시피의
             * 재료도 함께 삭제한다.
             */
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            /**
             * 재료명
             *
             * 레시피에 실제로 사용되는 재료의 이름이다.
             *
             * 예:
             *
             * 강력분
             * 설탕
             * 버터
             * 우유
             * 에스프레소
             * 바닐라시럽
             *
             * 현재는 별도의 원재료 마스터 테이블과
             * 연결하지 않고 직접 입력 방식으로 관리한다.
             *
             * 향후 재고 관리 기능이 추가되는 경우
             * 별도의 원재료 관리 구조와 연결할 수 있다.
             */
            $table->string('name');

            /**
             * 재료 사용량
             *
             * 정수뿐만 아니라 소수 단위의 사용량도
             * 저장할 수 있도록 decimal을 사용한다.
             *
             * 예:
             *
             * 250 g
             * 12.5 g
             * 0.5 ea
             * 1.25 L
             *
             * 실제 입력값은 0보다 큰 값만 허용하도록
             * Laravel에서 검증한다.
             */
            $table->decimal('quantity', 10, 3);

            /**
             * 재료 사용 단위
             *
             * 예:
             *
             * g
             * kg
             * ml
             * L
             * ea
             * tsp
             * tbsp
             * shot
             *
             * DB enum으로 고정하지 않는다.
             *
             * 화면에서는 자주 사용하는 단위를
             * 드롭다운으로 제공하고,
             * 목록에 없는 경우 사용자가 직접
             * 새로운 단위를 입력할 수 있도록 한다.
             *
             * 최종적으로 선택하거나 직접 입력한
             * 단위 문자열만 이 컬럼에 저장한다.
             */
            $table->string('unit');

            /**
             * 재료별 추가 설명
             *
             * 특정 재료의 상태나 사용 방법 등
             * 추가 정보가 필요한 경우 사용한다.
             *
             * 예:
             *
             * 실온 상태
             * 차갑게 보관
             * 녹인 버터 사용
             * 2샷 추출
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('note')->nullable();

            /**
             * 재료 표시 순서
             *
             * 레시피 화면에서 재료를 보여주는
             * 순서를 관리한다.
             *
             * 숫자가 작은 재료부터 먼저 표시한다.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 레시피별 재료 표시 순서 조회용 인덱스
             *
             * 특정 레시피의 재료 목록을
             * 정해진 순서대로 조회할 때 사용한다.
             */
            $table->index([
                'recipe_id',
                'sort_order',
            ]);
        });
    }

    /**
     * 레시피 재료 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};