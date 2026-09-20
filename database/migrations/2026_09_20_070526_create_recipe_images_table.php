<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 레시피 이미지 테이블 생성
     *
     * 레시피에 첨부되는 참고 이미지를 관리한다.
     *
     * 실제 이미지 파일 자체를 DB에 저장하지 않고
     * Laravel storage에 파일을 저장한 뒤
     * 해당 파일의 경로와 필요한 정보만 DB에 저장한다.
     *
     * 하나의 레시피에 여러 이미지를 등록할 수 있으며
     * 완성 이미지, 공정 참고 이미지 등으로
     * 확장해서 사용할 수 있다.
     */
    public function up(): void
    {
        Schema::create('recipe_images', function (Blueprint $table) {

            /**
             * 레시피 이미지 고유 ID
             */
            $table->id();

            /**
             * 이미지가 속한 레시피
             *
             * recipes.id를 참조한다.
             *
             * 이미지는 레시피에 종속되는 정보이므로
             * 레시피가 실제 삭제될 경우
             * 이미지 정보도 함께 삭제한다.
             *
             * 실제 storage 파일 삭제는
             * Laravel 서비스에서 함께 처리한다.
             */
            $table->foreignId('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            /**
             * 이미지 파일 경로
             *
             * Laravel storage에 저장된
             * 이미지 파일의 상대 경로를 저장한다.
             *
             * 예:
             *
             * recipes/15/abc123.webp
             *
             * 서버 주소 전체를 저장하지 않고
             * 상대 경로만 저장하여
             * 도메인이나 저장 방식이 변경되어도
             * 대응하기 쉽게 한다.
             */
            $table->string('file_path');

            /**
             * 이미지 종류
             *
             * 이미지의 용도를 구분하기 위한 값이다.
             *
             * 예:
             *
             * reference
             * finished
             * process
             *
             * DB enum으로 고정하지 않고 문자열로 저장한다.
             *
             * 허용되는 이미지 종류는 Laravel에서 검증하여
             * 향후 새로운 종류가 추가되어도
             * DB 구조를 변경하지 않도록 한다.
             */
            $table->string('image_type')
                ->default('reference');

            /**
             * 이미지 설명
             *
             * 사진에 대한 간단한 설명이나
             * 참고사항을 저장한다.
             *
             * 예:
             *
             * 완성 후 정면 모습
             * 성형 완료 상태
             * 최종 발효 기준 상태
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->string('caption')
                ->nullable();

            /**
             * 이미지 표시 순서
             *
             * 하나의 레시피에 여러 이미지가 있을 때
             * 화면에 표시되는 순서를 관리한다.
             *
             * 숫자가 작은 이미지부터 먼저 표시한다.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();

            /**
             * 레시피별 이미지 조회용 인덱스
             *
             * 특정 레시피의 이미지를
             * 종류와 표시 순서에 따라 조회할 때 사용한다.
             */
            $table->index([
                'recipe_id',
                'image_type',
                'sort_order',
            ]);
        });
    }

    /**
     * 레시피 이미지 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_images');
    }
};