<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 일일 매출 테이블 생성
     *
     * 점포별 하루 매출 입력을 하나의 묶음으로 관리한다.
     *
     * 실제 제품별 판매수량, 판매가격, 할인금액,
     * 기프트 및 무상제공 등의 상세 정보는
     * sale_items 테이블에서 관리한다.
     *
     * 직원이 여러 제품의 판매수량을 한 화면에서
     * 입력하고 한 번에 저장할 수 있도록
     * 일일 매출 헤더와 제품별 상세를 분리한다.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {

            /**
             * 일일 매출 고유 ID
             */
            $table->id();

            /**
             * 매출이 발생한 점포
             *
             * stores.id를 참조한다.
             *
             * 매출은 반드시 실제 점포에 귀속되므로
             * NULL을 허용하지 않는다.
             *
             * 본사 직원이나 최고관리자가 입력 또는
             * 수정하더라도 매출 자체의 점포 소속은
             * 실제 매출 발생 점포를 유지한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 매출 기준일
             *
             * 해당 일일 매출표가 어느 날짜의
             * 영업 실적에 해당하는지 저장한다.
             *
             * 데이터 생성일인 created_at과
             * 실제 영업일은 다를 수 있으므로
             * 별도의 날짜 컬럼으로 관리한다.
             */
            $table->date('sales_date');

            /**
             * 매출 입력 상태
             *
             * draft
             * → 임시저장 상태
             *
             * confirmed
             * → 최종확정 상태
             *
             * DB enum으로 고정하지 않고 문자열로 저장하며
             * 허용 가능한 상태와 상태 변경 규칙은
             * Laravel에서 검증한다.
             *
             * 향후 필요하면 reopened, corrected 등의
             * 상태도 DB 구조 변경 없이 확장할 수 있다.
             */
            $table->string('status')
                ->default('draft');

            /**
             * 최종확정 일시
             *
             * 매출표가 최종확정된 시각을 저장한다.
             *
             * 임시저장 상태에서는 NULL이며
             * 최종확정 시 Laravel에서 현재 시각을 기록한다.
             */
            $table->timestamp('confirmed_at')
                ->nullable();

            /**
             * 최종확정 처리자
             *
             * 해당 일일 매출을 최종확정한
             * users.id를 저장한다.
             *
             * 아직 확정되지 않았다면 NULL이다.
             */
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 매출표 전체 메모
             *
             * 해당 날짜의 영업 상황이나
             * 매출 관련 특이사항을 기록한다.
             *
             * 예:
             *
             * 행사 첫날
             * 우천으로 방문객 감소
             * 일부 제품 조기 품절
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('note')
                ->nullable();

            /**
             * 최초 입력자
             *
             * 일일 매출표를 처음 생성한
             * users.id를 저장한다.
             *
             * 실제 매출 발생 점포와 입력자의 소속이
             * 반드시 동일하다고 가정하지 않는다.
             *
             * 향후 권한을 가진 본사 직원 또는
             * 최고관리자가 대신 입력할 수도 있기 때문이다.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 마지막 수정자
             *
             * 일일 매출표 또는 상세 판매정보를
             * 마지막으로 수정한 사용자를 저장한다.
             *
             * 최초 생성 이후 수정되지 않았다면 NULL이다.
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
             * 동일 점포의 동일 영업일에
             * 일일 매출표가 여러 개 생성되는 것을 방지한다.
             *
             * 제품별 판매 데이터는 하나의 sales 아래에
             * 여러 sale_items로 저장한다.
             */
            $table->unique([
                'store_id',
                'sales_date',
            ]);

            /**
             * 점포별 기간 매출 조회용 인덱스
             *
             * 일별, 월별, 기간별 매출을
             * 조회할 때 사용한다.
             */
            $table->index([
                'store_id',
                'sales_date',
                'status',
            ]);

            /**
             * 매출 상태별 조회용 인덱스
             *
             * 아직 확정되지 않은 매출표 등을
             * 조회할 때 사용한다.
             */
            $table->index([
                'status',
                'sales_date',
            ]);
        });
    }

    /**
     * 일일 매출 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};