<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 레시피 제조 순서 테이블 생성
     *
     * 하나의 레시피에 포함되는 여러 제조 단계를
     * 순서대로 관리한다.
     *
     * 제빵, 케이크, 디저트뿐만 아니라
     * 커피 및 음료 제조 과정에도
     * 동일한 구조를 사용할 수 있다.
     */
    public function up(): void
    {
        Schema::create('recipe_steps', function (Blueprint $table) {

            /**
             * 제조 단계 고유 ID
             */
            $table->id();

            /**
             * 제조 단계가 속한 레시피
             *
             * recipes.id를 참조한다.
             *
             * 제조 단계는 해당 레시피에 종속되는 정보이므로
             * 레시피가 실제 삭제될 경우
             * 제조 단계도 함께 삭제한다.
             */
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            /**
             * 제조 단계 제목
             *
             * 각 작업 단계를 쉽게 구분하기 위한
             * 짧은 제목을 저장한다.
             *
             * 예:
             *
             * 반죽
             * 1차 발효
             * 분할 및 성형
             * 2차 발효
             * 굽기
             * 에스프레소 추출
             * 우유 혼합
             *
             * 제목 없이 설명만 작성하는 것도
             * 가능하도록 NULL을 허용한다.
             */
            $table->string('title')->nullable();

            /**
             * 제조 방법
             *
             * 해당 단계에서 실제로 수행해야 하는
             * 작업 내용을 저장한다.
             *
             * 예:
             *
             * 모든 재료를 믹싱볼에 넣고
             * 저속 3분, 고속 5분간 믹싱한다.
             *
             * 또는
             *
             * 에스프레소 2샷을 추출한 뒤
             * 우유 180ml와 혼합한다.
             *
             * 제조 단계의 핵심 정보이므로
             * 반드시 입력하도록 한다.
             */
            $table->text('description');

            /**
             * 제조 단계 표시 순서
             *
             * 레시피 화면에서 제조 과정을
             * 어떤 순서로 보여줄지 결정한다.
             *
             * 예:
             *
             * 1 → 반죽
             * 2 → 1차 발효
             * 3 → 분할
             * 4 → 성형
             *
             * 사용자가 화면에서 제조 단계를
             * 위아래로 이동하면 이 값을 변경한다.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 레시피별 제조 순서 조회용 인덱스
             *
             * 특정 레시피의 제조 단계를
             * 정해진 순서대로 조회할 때 사용한다.
             */
            $table->index([
                'recipe_id',
                'sort_order',
            ]);
        });
    }

    /**
     * 레시피 제조 순서 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_steps');
    }
};