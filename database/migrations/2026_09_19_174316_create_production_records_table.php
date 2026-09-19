<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 생산 기록 테이블 생성
     *
     * 직원이 실제로 생산한 제품과 생산 수량을 기록한다.
     *
     * 점포, 부서, 제품, 실제 작업자, 업무 날짜를 함께 저장하여
     * 직원의 소속이 나중에 변경되더라도 당시의 생산 기록을
     * 정확하게 유지할 수 있도록 한다.
     *
     * 주간, 월간, 연간 생산량 및 요일별 평균 등의 통계는
     * 이 테이블의 원본 데이터를 기반으로 계산한다.
     */
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {

            /**
             * 생산 기록 고유 ID
             *
             * 각각의 생산 기록을 구분하기 위한 기본키(PK)이다.
             */
            $table->id();

            /**
             * 업무 날짜
             *
             * 제품을 실제로 생산한 업무 날짜이다.
             *
             * created_at은 시스템에 데이터를 입력한 시간이므로
             * 실제 생산 날짜를 나타내는 work_date와 구분한다.
             *
             * 과거 생산량 조회 및 일별, 요일별, 월별 등의
             * 통계를 계산할 때 기준 날짜로 사용한다.
             */
            $table->date('work_date');

            /**
             * 생산 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 작업자의 현재 소속 점포가 변경되더라도
             * 생산 당시 어느 점포에서 작업했는지 보존하기 위해
             * 생산 기록 자체에 store_id를 저장한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores');

            /**
             * 생산 부서
             *
             * 제품을 생산한 당시의 부서를 저장한다.
             *
             * 기본적으로 다음 값을 사용한다.
             *
             * kitchen    = 주방
             * hall       = 홀
             * operations = 운영진 / 운영관리
             *
             * 사용자의 현재 department가 변경되더라도
             * 과거 생산 당시의 부서 정보를 유지할 수 있도록
             * 생산 기록 자체에 저장한다.
             */
            $table->string('department');

            /**
             * 생산 제품
             *
             * products 테이블의 id를 참조한다.
             *
             * 제품명을 생산 기록에 직접 저장하지 않고
             * product_id를 통해 제품 마스터와 연결한다.
             */
            $table->foreignId('product_id')
                ->constrained('products');

            /**
             * 실제 생산 작업자
             *
             * users 테이블의 id를 참조한다.
             *
             * 해당 제품을 실제로 생산한 직원을 의미한다.
             *
             * worker_id는 데이터를 시스템에 입력한 사람이 아니라
             * 실제 생산 작업을 수행한 사람을 나타낸다.
             */
            $table->foreignId('worker_id')
                ->constrained('users');

            /**
             * 생산 수량
             *
             * 해당 작업자가 실제로 생산한 완제품의 수량이다.
             *
             * 현재 생산 관리 대상은 완성된 빵의 개수이므로
             * 정수(integer) 타입으로 관리한다.
             *
             * 예)
             * 소금빵 30개 → quantity = 30
             * 크루아상 20개 → quantity = 20
             *
             * 0 이하의 수량을 허용할지 여부는
             * Laravel Form Request에서 엄격하게 검증한다.
             */
            $table->unsignedInteger('quantity');

            /**
             * 최초 입력자
             *
             * users 테이블의 id를 참조한다.
             *
             * worker_id는 실제 생산한 직원이고
             * created_by는 시스템에 데이터를 입력한 직원이다.
             *
             * 일반적인 본인 입력:
             * worker_id = created_by
             *
             * 헤드 셰프 등의 대리 입력:
             * worker_id != created_by
             *
             * 두 값을 비교하여 대리 입력 여부를 확인할 수 있으므로
             * 별도의 proxy 여부 컬럼은 저장하지 않는다.
             */
            $table->foreignId('created_by')
                ->constrained('users');

            /**
             * 마지막 수정자
             *
             * users 테이블의 id를 참조한다.
             *
             * 생산 기록이 수정된 경우 마지막으로 수정한
             * 사용자를 추적하기 위해 사용한다.
             *
             * 한 번도 수정되지 않은 생산 기록은
             * 수정자가 없으므로 nullable로 관리한다.
             */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users');

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 생산 기록이 시스템에 최초 입력된 시간
             * updated_at = 생산 기록이 마지막으로 수정된 시간
             *
             * 실제 생산 날짜는 created_at이 아니라
             * 별도의 work_date를 기준으로 판단한다.
             */
            $table->timestamps();

            /**
             * 시스템상 삭제 일시 (Soft Delete)
             *
             * 잘못 입력된 생산 기록을 삭제하더라도
             * 데이터를 DB에서 즉시 제거하지 않는다.
             *
             * 향후 audit_logs와 함께 사용하여 누가 어떤 생산 기록을
             * 삭제하거나 수정했는지 추적할 수 있도록 한다.
             */
            $table->softDeletes();

            /**
             * 생산 통계 조회를 위한 복합 인덱스
             *
             * 특정 점포의 특정 제품에 대해 날짜별 생산량을
             * 조회하는 작업이 매우 자주 발생할 수 있다.
             *
             * 주간, 월간, 요일별 생산량 및 평균 생산량 등을
             * 계산할 때 효율적으로 조회할 수 있도록 한다.
             */
            $table->index([
                'store_id',
                'product_id',
                'work_date',
            ]);

            /**
             * 작업자별 생산 기록 조회를 위한 복합 인덱스
             *
             * 특정 직원이 특정 날짜에 어떤 제품을 얼마나
             * 생산했는지 조회할 때 사용한다.
             */
            $table->index([
                'worker_id',
                'work_date',
            ]);
        });
    }

    /**
     * 생산 기록 테이블 삭제
     *
     * Migration을 rollback할 때
     * production_records 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};