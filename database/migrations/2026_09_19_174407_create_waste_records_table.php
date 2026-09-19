<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 폐기 기록 테이블 생성
     *
     * 직원이 실제로 폐기한 제품과 폐기 수량,
     * 폐기 사유 등의 정보를 기록한다.
     *
     * 점포, 부서, 제품, 실제 작업자, 업무 날짜를 함께 저장하여
     * 직원의 소속이 나중에 변경되더라도 당시의 폐기 기록을
     * 정확하게 유지할 수 있도록 한다.
     *
     * 폐기율, 기간별 폐기량, 제품별 폐기 통계 등은
     * 이 테이블의 원본 데이터를 기반으로 계산한다.
     */
    public function up(): void
    {
        Schema::create('waste_records', function (Blueprint $table) {

            /**
             * 폐기 기록 고유 ID
             *
             * 각각의 폐기 기록을 구분하기 위한 기본키(PK)이다.
             */
            $table->id();

            /**
             * 업무 날짜
             *
             * 제품을 실제로 폐기한 업무 날짜이다.
             *
             * created_at은 시스템에 데이터를 입력한 시간이므로
             * 실제 폐기 날짜를 나타내는 work_date와 구분한다.
             *
             * 일별, 주간, 월간, 요일별 폐기량 및
             * 폐기율 통계를 계산할 때 기준 날짜로 사용한다.
             */
            $table->date('work_date');

            /**
             * 폐기 발생 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 작업자의 현재 소속 점포가 변경되더라도
             * 폐기 당시 어느 점포에서 작업했는지 보존하기 위해
             * 폐기 기록 자체에 store_id를 저장한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores');

            /**
             * 폐기 발생 부서
             *
             * 폐기가 발생한 당시의 부서를 저장한다.
             *
             * 기본적으로 다음 값을 사용한다.
             *
             * kitchen    = 주방
             * hall       = 홀
             * operations = 운영진 / 운영관리
             *
             * 사용자의 현재 department가 변경되더라도
             * 과거 폐기 당시의 부서 정보를 유지할 수 있도록
             * 폐기 기록 자체에 저장한다.
             */
            $table->string('department');

            /**
             * 폐기 제품
             *
             * products 테이블의 id를 참조한다.
             *
             * 제품명을 폐기 기록에 직접 저장하지 않고
             * product_id를 통해 제품 마스터와 연결한다.
             */
            $table->foreignId('product_id')
                ->constrained('products');

            /**
             * 실제 폐기 작업자
             *
             * users 테이블의 id를 참조한다.
             *
             * 해당 제품을 실제로 폐기 처리한 직원을 의미한다.
             *
             * worker_id는 시스템에 데이터를 입력한 사람이 아니라
             * 실제 폐기 업무를 수행한 사람을 나타낸다.
             */
            $table->foreignId('worker_id')
                ->constrained('users');

            /**
             * 폐기 수량
             *
             * 해당 작업자가 실제로 폐기한 완제품의 수량이다.
             *
             * 현재 폐기 관리 대상은 완성된 빵의 개수이므로
             * 정수(integer) 타입으로 관리한다.
             *
             * 예)
             * 소금빵 3개 폐기 → quantity = 3
             * 크루아상 2개 폐기 → quantity = 2
             *
             * 0 이하의 수량을 허용하지 않는 등의 검증은
             * Laravel Form Request에서 엄격하게 처리한다.
             */
            $table->unsignedInteger('quantity');

            /**
             * 폐기 사유
             *
             * 제품이 폐기된 원인을 기록한다.
             *
             * 기본적으로 다음과 같은 값을 사용할 수 있다.
             *
             * unsold     = 미판매
             * damaged    = 파손 또는 상품성 저하
             * expired    = 사용 또는 판매 가능 기간 초과
             * production = 생산 과정에서 발생한 폐기
             * other      = 기타
             *
             * 실제 허용 가능한 값은 Laravel에서 검증하여
             * 임의의 값이 저장되지 않도록 관리한다.
             *
             * 초기 운영 과정에서 폐기 사유를 기록하지 않는 경우도
             * 고려하여 nullable로 관리한다.
             */
            $table->string('reason')->nullable();

            /**
             * 폐기 상세 메모
             *
             * 폐기 사유만으로 설명하기 어려운 내용을
             * 추가로 기록하기 위한 자유 입력 메모이다.
             *
             * 예)
             * "오븐 작업 중 제품 일부가 심하게 타서 폐기"
             * "마감 후 판매되지 않은 제품 폐기"
             *
             * reason이 other인 경우 메모 입력을 필수로 하는 등의
             * 업무 규칙은 Laravel Form Request에서 검증할 수 있다.
             */
            $table->text('memo')->nullable();

            /**
             * 최초 입력자
             *
             * users 테이블의 id를 참조한다.
             *
             * worker_id는 실제 폐기 작업을 수행한 직원이고
             * created_by는 시스템에 데이터를 입력한 직원이다.
             *
             * 일반적인 본인 입력:
             * worker_id = created_by
             *
             * 관리자 등의 대리 입력:
             * worker_id != created_by
             *
             * 두 값을 비교하여 대리 입력 여부를 판단할 수 있으므로
             * 별도의 proxy 여부 컬럼은 저장하지 않는다.
             */
            $table->foreignId('created_by')
                ->constrained('users');

            /**
             * 마지막 수정자
             *
             * users 테이블의 id를 참조한다.
             *
             * 폐기 기록이 수정된 경우 마지막으로 수정한
             * 사용자를 추적하기 위해 사용한다.
             *
             * 한 번도 수정되지 않은 기록은 수정자가 없으므로
             * nullable로 관리한다.
             */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users');

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 폐기 기록이 시스템에 최초 입력된 시간
             * updated_at = 폐기 기록이 마지막으로 수정된 시간
             *
             * 실제 폐기 날짜는 created_at이 아니라
             * 별도의 work_date를 기준으로 판단한다.
             */
            $table->timestamps();

            /**
             * 시스템상 삭제 일시 (Soft Delete)
             *
             * 잘못 입력된 폐기 기록을 삭제하더라도
             * 데이터를 DB에서 즉시 제거하지 않는다.
             *
             * 향후 audit_logs와 함께 사용하여 누가 어떤 폐기 기록을
             * 삭제하거나 수정했는지 추적할 수 있도록 한다.
             */
            $table->softDeletes();

            /**
             * 폐기 통계 조회를 위한 복합 인덱스
             *
             * 특정 점포의 특정 제품에 대해 날짜별 폐기량을
             * 조회하는 작업이 자주 발생할 수 있다.
             *
             * 기간별 폐기량, 폐기율 및 제품별 폐기 통계를
             * 효율적으로 계산할 수 있도록 한다.
             */
            $table->index([
                'store_id',
                'product_id',
                'work_date',
            ]);

            /**
             * 작업자별 폐기 기록 조회를 위한 복합 인덱스
             *
             * 특정 직원이 특정 날짜에 어떤 제품을 얼마나
             * 폐기했는지 조회할 때 사용한다.
             */
            $table->index([
                'worker_id',
                'work_date',
            ]);
        });
    }

    /**
     * 폐기 기록 테이블 삭제
     *
     * Migration을 rollback할 때
     * waste_records 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_records');
    }
};