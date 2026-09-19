<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 변경 이력 테이블 생성
     *
     * Till White 시스템에서 중요한 데이터가 생성, 수정, 삭제,
     * 복구되었을 때 변경 이력을 기록한다.
     *
     * 특히 생산 및 폐기 기록처럼 수량 변경이 중요한 데이터에 대해
     * 누가 어떤 데이터를 어떻게 변경했는지 추적할 수 있도록 한다.
     *
     * 기존 값을 덮어쓰는 것만으로 끝내지 않고 변경 전 값과
     * 변경 후 값을 함께 보관하여 과거 변경 내용을 확인할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {

            /**
             * 변경 이력 고유 ID
             *
             * 각각의 변경 이력을 구분하기 위한 기본키(PK)이다.
             */
            $table->id();

            /**
             * 작업을 수행한 사용자
             *
             * users 테이블의 id를 참조한다.
             *
             * 실제 데이터의 worker_id와는 다른 개념이며,
             * 해당 생성, 수정, 삭제 등의 작업을 시스템에서
             * 실제로 수행한 사용자를 기록한다.
             */
            $table->foreignId('user_id')
                ->constrained('users');

            /**
             * 작업 당시 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 점포와 직접 관련된 데이터의 변경 이력인 경우
             * 어느 점포에서 발생한 변경인지 기록한다.
             *
             * 시스템 전체 설정처럼 특정 점포와 관계없는
             * 변경도 존재할 수 있으므로 nullable로 관리한다.
             */
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores');

            /**
             * 변경 작업 종류
             *
             * 해당 데이터에 어떤 작업이 수행되었는지 기록한다.
             *
             * 기본적으로 다음 값을 사용한다.
             *
             * create  = 데이터 생성
             * update  = 데이터 수정
             * delete  = 데이터 삭제 처리
             * restore = 삭제된 데이터 복구
             *
             * 실제 허용 가능한 값은 Laravel에서 검증하여
             * 임의의 action 값이 저장되지 않도록 관리한다.
             */
            $table->string('action');

            /**
             * 변경 대상 테이블
             *
             * 어떤 종류의 데이터가 변경되었는지 기록한다.
             *
             * 예)
             * production_records
             * waste_records
             * work_schedules
             * products
             * users
             *
             * audit_logs 하나로 여러 종류의 데이터 변경 이력을
             * 관리하기 위해 대상 테이블명을 함께 저장한다.
             */
            $table->string('table_name');

            /**
             * 변경 대상 데이터 ID
             *
             * table_name에 기록된 테이블에서
             * 실제 변경된 데이터의 기본키(PK)를 저장한다.
             *
             * 예)
             * table_name = production_records
             * record_id = 15
             *
             * → production_records 테이블의 id 15번 데이터에 대한
             *   변경 이력이라는 의미이다.
             */
            $table->unsignedBigInteger('record_id');

            /**
             * 변경 전 데이터
             *
             * 수정 또는 삭제되기 전의 데이터를 JSON 형태로 저장한다.
             *
             * 예)
             * quantity가 30에서 40으로 수정되었다면
             * old_values에는 변경 전 quantity = 30을 기록한다.
             *
             * 데이터 생성(create)의 경우에는 변경 전 값이
             * 존재하지 않으므로 nullable로 관리한다.
             */
            $table->json('old_values')->nullable();

            /**
             * 변경 후 데이터
             *
             * 생성 또는 수정된 이후의 데이터를 JSON 형태로 저장한다.
             *
             * 예)
             * quantity가 30에서 40으로 수정되었다면
             * new_values에는 변경 후 quantity = 40을 기록한다.
             *
             * 삭제 작업처럼 변경 후 데이터가 필요하지 않은 경우도
             * 존재할 수 있으므로 nullable로 관리한다.
             */
            $table->json('new_values')->nullable();

            /**
             * 변경 사유
             *
             * 사용자가 데이터를 수정하거나 삭제한 이유를
             * 추가로 기록할 수 있도록 한다.
             *
             * 예)
             * "생산 수량 오입력 수정"
             * "폐기 수량 중복 입력으로 삭제"
             *
             * 모든 작업에서 반드시 필요한 값은 아니므로
             * nullable로 관리한다.
             *
             * 향후 특정 작업에서는 Laravel validation을 통해
             * 변경 사유 입력을 필수로 요구할 수도 있다.
             */
            $table->text('reason')->nullable();

            /**
             * 변경 발생 일시
             *
             * audit_logs는 이미 발생한 변경 사실을 기록하는
             * 이력 데이터이므로 created_at만 저장한다.
             *
             * 변경 이력 자체를 일반 업무 데이터처럼 수정하지 않으므로
             * updated_at은 별도로 생성하지 않는다.
             */
            $table->timestamp('created_at')->useCurrent();

            /**
             * 특정 데이터의 변경 이력 조회를 위한 복합 인덱스
             *
             * 상세보기 화면에서 특정 생산 기록이나 폐기 기록의
             * 전체 변경 이력을 조회할 때 사용한다.
             */
            $table->index([
                'table_name',
                'record_id',
            ]);

            /**
             * 사용자별 변경 이력 조회를 위한 인덱스
             *
             * 특정 사용자가 어떤 데이터를 생성, 수정 또는 삭제했는지
             * 확인할 때 효율적으로 조회할 수 있도록 한다.
             */
            $table->index('user_id');

            /**
             * 점포별 변경 이력 조회를 위한 인덱스
             *
             * 특정 점포에서 발생한 변경 이력을 조회할 때
             * 효율적으로 검색할 수 있도록 한다.
             */
            $table->index('store_id');
        });
    }

    /**
     * 변경 이력 테이블 삭제
     *
     * Migration을 rollback할 때
     * audit_logs 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};