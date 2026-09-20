<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 프로모션 기본정보 테이블 생성
     *
     * 제품 할인, 행사 판매 등
     * 일정 기간 동안 적용되는 프로모션의
     * 기본 정보를 관리한다.
     *
     * 실제 대상 제품과 할인 조건은
     * 별도의 프로모션 제품 테이블에서 관리한다.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {

            /**
             * 프로모션 고유 ID
             */
            $table->id();

            /**
             * 프로모션 적용 점포
             *
             * 특정 점포 프로모션인 경우
             * stores.id를 저장한다.
             *
             * NULL인 경우 모든 점포에 적용되는
             * 본사 공통 프로모션을 의미한다.
             *
             * 점포가 폐점되더라도 과거 프로모션
             * 기록을 유지해야 하므로 삭제를 제한한다.
             */
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 프로모션명
             *
             * 화면에서 직원이 쉽게 확인할 수 있는
             * 프로모션 이름을 저장한다.
             *
             * 예:
             *
             * 가을 신제품 할인
             * 우유식빵 출시 프로모션
             * 크리스마스 케이크 행사
             */
            $table->string('name');

            /**
             * 프로모션 설명
             *
             * 행사 목적이나 적용 조건 등에 대한
             * 추가 설명을 저장한다.
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('description')
                ->nullable();

            /**
             * 프로모션 시작일
             *
             * 해당 프로모션이 실제 판매에
             * 적용되기 시작하는 날짜이다.
             */
            $table->date('start_date');

            /**
             * 프로모션 종료일
             *
             * 해당 프로모션이 적용되는
             * 마지막 날짜이다.
             *
             * 종료일이 정해지지 않은 프로모션도
             * 사용할 수 있도록 NULL을 허용한다.
             */
            $table->date('end_date')
                ->nullable();

            /**
             * 프로모션 활성 여부
             *
             * 기간이 남아 있더라도 운영상 필요하면
             * 프로모션을 즉시 비활성화할 수 있다.
             *
             * 실제 적용 여부는 Laravel에서
             * 활성 상태와 적용 기간을 함께 확인한다.
             */
            $table->boolean('is_active')
                ->default(true);

            /**
             * 프로모션 최초 등록자
             *
             * 프로모션을 생성한 사용자의
             * users.id를 저장한다.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 프로모션 마지막 수정자
             *
             * 프로모션 정보가 수정된 경우
             * 마지막 수정 사용자를 저장한다.
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
             * 점포별 프로모션 기간 조회용 인덱스
             *
             * 특정 날짜에 현재 적용 가능한
             * 프로모션을 조회할 때 사용한다.
             */
            $table->index([
                'store_id',
                'is_active',
                'start_date',
                'end_date',
            ]);
        });
    }

    /**
     * 프로모션 기본정보 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};