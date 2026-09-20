<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 생산·폐기·로스 기록 테이블 생성
     *
     * 직원이 특정 날짜에 특정 제품에 대해 작업한
     * 생산량, 폐기량, 로스량을 하나의 기록으로 관리한다.
     *
     * 생산·폐기·로스를 각각 별도의 기록으로 나누지 않고
     * 하나의 입력 화면에서 함께 입력하고 저장한다.
     *
     * 이후 날짜별, 제품별, 직원별, 점포별, 부서별
     * 생산·폐기·로스 통계 및 그래프의 기준 데이터로 사용한다.
     */
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {

            /**
             * 생산·폐기·로스 기록 고유 ID
             */
            $table->id();

            /**
             * 작업 날짜
             *
             * 실제 생산·폐기·로스가 발생한 날짜이다.
             *
             * created_at과 별도로 관리하여
             * 나중에 기록을 입력하더라도 실제 작업일을 보존한다.
             */
            $table->date('work_date');

            /**
             * 작업이 이루어진 점포
             *
             * stores.id를 참조한다.
             *
             * 직원이 이후 다른 점포로 이동하더라도
             * 과거 기록이 어느 점포에서 발생했는지 보존한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 작업 당시 부서
             *
             * 예:
             *
             * kitchen
             * → 주방
             *
             * hall
             * → 홀
             *
             * 직원의 현재 department가 나중에 변경되어도
             * 과거 작업 당시의 부서 정보를 그대로 보존한다.
             */
            $table->string('department');

            /**
             * 대상 제품
             *
             * products.id를 참조한다.
             *
             * 생산·폐기·로스 기록이 존재하는 제품은
             * 물리적으로 삭제하지 못하도록 한다.
             */
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            /**
             * 실제 작업자
             *
             * 해당 생산·폐기·로스 작업을 실제로 수행한
             * 직원의 users.id를 저장한다.
             *
             * 기록을 대신 입력한 사람과
             * 실제 작업자는 서로 다를 수 있다.
             */
            $table->foreignId('worker_id')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 생산량
             *
             * 정상적으로 생산 완료된 제품 수량이다.
             *
             * 입력하지 않은 경우 0으로 처리한다.
             */
            $table->unsignedInteger('production_quantity')
                ->default(0);

            /**
             * 폐기량
             *
             * 생산이 완료된 제품 중
             * 판매하지 못하고 폐기된 수량이다.
             *
             * 입력하지 않은 경우 0으로 처리한다.
             */
            $table->unsignedInteger('waste_quantity')
                ->default(0);

            /**
             * 로스량
             *
             * 생산 과정에서 불량이나 작업 실수 등으로
             * 정상 제품으로 완성되지 못한 수량이다.
             *
             * 입력하지 않은 경우 0으로 처리한다.
             */
            $table->unsignedInteger('loss_quantity')
                ->default(0);

            /**
             * 폐기 사유
             *
             * 폐기량이 1개 이상인 경우
             * 폐기가 발생한 대표적인 이유를 저장한다.
             *
             * 실제 필수 여부와 허용 가능한 값은
             * Laravel에서 조건부로 검증한다.
             *
             * 폐기량이 0인 경우 NULL을 허용한다.
             */
            $table->string('waste_reason')->nullable();

            /**
             * 폐기 상세 내용
             *
             * 폐기가 발생한 구체적인 이유나
             * 특이사항을 기록할 때 사용한다.
             */
            $table->text('waste_note')->nullable();

            /**
             * 로스 사유
             *
             * 로스량이 1개 이상인 경우
             * 로스가 발생한 대표적인 이유를 저장한다.
             *
             * 예:
             *
             * shaping_failure
             * → 성형 불량
             *
             * baking_failure
             * → 오븐 / 소성 불량
             *
             * dough_failure
             * → 반죽 불량
             *
             * weighing_error
             * → 계량 실수
             *
             * work_error
             * → 작업 실수
             *
             * other
             * → 기타
             *
             * 실제 필수 여부와 허용 가능한 값은
             * Laravel에서 조건부로 검증한다.
             */
            $table->string('loss_reason')->nullable();

            /**
             * 로스 상세 내용
             *
             * 로스가 발생한 구체적인 원인이나
             * 특이사항을 기록할 때 사용한다.
             */
            $table->text('loss_note')->nullable();

            /**
             * 기록 전체 메모
             *
             * 생산량 자체에 대한 특이사항 등
             * 폐기 또는 로스 사유와 관계없는
             * 일반적인 메모를 저장한다.
             */
            $table->text('note')->nullable();

            /**
             * 최초 입력자
             *
             * 실제 작업자와 입력자가 다를 수 있으므로
             * worker_id와 별도로 관리한다.
             *
             * 예:
             *
             * worker_id
             * → 실제 생산 작업자
             *
             * created_by
             * → 해당 기록을 시스템에 입력한 직원
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 기록이 수정된 경우
             * 마지막으로 수정한 직원을 저장한다.
             *
             * 아직 수정된 적이 없는 경우 NULL이다.
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
             * 소프트 삭제 일시
             *
             * 생산·폐기·로스 기록을 삭제하더라도
             * 실제 데이터를 DB에서 제거하지 않고 삭제 상태로 보존한다.
             *
             * 잘못 삭제된 기록의 복구와 과거 기록 추적,
             * 감사 로그 확인 등에 활용한다.
             */
            $table->softDeletes();

            /**
             * 날짜 및 점포별 조회용 인덱스
             *
             * 특정 점포의 일별/월별 생산·폐기·로스
             * 조회 및 통계에 사용한다.
             */
            $table->index([
                'store_id',
                'work_date',
            ]);

            /**
             * 점포 및 부서별 조회용 인덱스
             *
             * 주방/홀 단위 생산·폐기·로스
             * 조회 및 통계에 사용한다.
             */
            $table->index([
                'store_id',
                'department',
                'work_date',
            ]);

            /**
             * 제품별 조회용 인덱스
             *
             * 특정 제품의 생산량, 폐기량, 로스량
             * 추이 및 그래프 조회에 사용한다.
             */
            $table->index([
                'product_id',
                'work_date',
            ]);

            /**
             * 직원별 조회용 인덱스
             *
             * 직원별 생산량 및 작업 실적
             * 조회와 통계에 사용한다.
             */
            $table->index([
                'worker_id',
                'work_date',
            ]);
        });
    }

    /**
     * 생산·폐기·로스 기록 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};