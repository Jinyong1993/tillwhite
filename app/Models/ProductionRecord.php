<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionRecord extends Model
{
    /**
     * Soft Delete 사용
     *
     * 생산·폐기·로스 기록을 실제 DB에서 바로 삭제하지 않고
     * deleted_at 값을 이용하여 삭제 상태로 관리한다.
     *
     * 잘못 삭제된 기록의 복구와 과거 기록 추적,
     * 감사 로그 확인 등을 위해 데이터를 보존한다.
     */
    use SoftDeletes;

    /**
     * 대량 할당 가능한 속성
     *
     * ProductionRecord::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     *
     * 실제 권한 및 입력값 검증은
     * Laravel의 Form Request와 Policy 등에서 별도로 처리한다.
     */
    protected $fillable = [
        /**
         * 실제 작업 날짜
         *
         * 생산·폐기·로스가 실제로 발생한 날짜를 저장한다.
         *
         * created_at은 시스템에 기록을 입력한 시간이므로
         * 실제 작업일을 의미하는 work_date와 구분한다.
         */
        'work_date',

        /**
         * 작업 점포 ID
         *
         * stores 테이블의 id를 참조한다.
         *
         * 직원이 이후 다른 점포로 이동하더라도
         * 작업 당시의 점포 정보를 보존한다.
         */
        'store_id',

        /**
         * 작업 당시 부서
         *
         * 기본 부서 코드:
         *
         * kitchen
         * → 주방
         *
         * hall
         * → 홀
         *
         * 사용자의 현재 부서와 별도로 저장하여
         * 과거 작업 당시의 부서 정보를 보존한다.
         */
        'department',

        /**
         * 대상 제품 ID
         *
         * products 테이블의 id를 참조한다.
         *
         * 어떤 제품에 대한 생산·폐기·로스 기록인지
         * 식별하기 위해 사용한다.
         */
        'product_id',

        /**
         * 실제 작업자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 기록을 시스템에 입력한 사람이 아니라
         * 실제 작업을 수행한 직원을 의미한다.
         *
         * 예:
         *
         * 직원 A가 제품을 생산하고
         * 헤드 셰프 B가 대신 입력한 경우
         *
         * worker_id
         * → 직원 A
         *
         * created_by
         * → 헤드 셰프 B
         */
        'worker_id',

        /**
         * 생산량
         *
         * 정상적으로 생산 완료된 제품의 수량을 저장한다.
         *
         * 입력하지 않은 경우 DB 기본값은 0이다.
         */
        'production_quantity',

        /**
         * 폐기량
         *
         * 생산이 완료된 제품 중 판매하지 못하고
         * 폐기된 제품의 수량을 저장한다.
         *
         * 입력하지 않은 경우 DB 기본값은 0이다.
         */
        'waste_quantity',

        /**
         * 로스량
         *
         * 생산 과정에서 불량이나 작업 실수 등으로
         * 정상 제품으로 완성되지 못한 수량을 저장한다.
         *
         * 입력하지 않은 경우 DB 기본값은 0이다.
         */
        'loss_quantity',

        /**
         * 폐기 사유
         *
         * 폐기량이 존재하는 경우
         * 대표적인 폐기 사유 코드를 저장한다.
         *
         * 폐기량에 따른 필수 여부 및 허용 가능한 값은
         * Laravel에서 조건부로 검증한다.
         */
        'waste_reason',

        /**
         * 폐기 상세 내용
         *
         * 폐기가 발생한 구체적인 이유나
         * 추가 설명이 필요한 경우 사용한다.
         */
        'waste_note',

        /**
         * 로스 사유
         *
         * 로스량이 존재하는 경우
         * 대표적인 로스 사유 코드를 저장한다.
         *
         * 로스량에 따른 필수 여부 및 허용 가능한 값은
         * Laravel에서 조건부로 검증한다.
         */
        'loss_reason',

        /**
         * 로스 상세 내용
         *
         * 로스가 발생한 구체적인 원인이나
         * 추가 설명이 필요한 경우 사용한다.
         */
        'loss_note',

        /**
         * 기록 전체 메모
         *
         * 폐기 또는 로스 사유와 직접 관계없는
         * 생산 작업 전체의 특이사항을 저장한다.
         */
        'note',

        /**
         * 최초 입력자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 실제 작업자와 시스템 입력자가 다를 수 있으므로
         * worker_id와 별도로 관리한다.
         */
        'created_by',

        /**
         * 마지막 수정자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 기록이 수정되었을 때 마지막으로 수정한
         * 사용자의 ID를 저장한다.
         *
         * 한 번도 수정되지 않은 경우 NULL이다.
         */
        'updated_by',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회한 값을 애플리케이션에서 사용할 때
     * 필요한 PHP 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [
            /**
             * 실제 작업 날짜
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환한다.
             */
            'work_date' => 'date',

            /**
             * 생산량
             *
             * 항상 정수 타입으로 사용한다.
             */
            'production_quantity' => 'integer',

            /**
             * 폐기량
             *
             * 항상 정수 타입으로 사용한다.
             */
            'waste_quantity' => 'integer',

            /**
             * 로스량
             *
             * 항상 정수 타입으로 사용한다.
             */
            'loss_quantity' => 'integer',
        ];
    }

    /**
     * 기록이 발생한 점포
     *
     * production_records.store_id가
     * stores.id를 참조한다.
     *
     * 예:
     *
     * $productionRecord->store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 대상 제품
     *
     * production_records.product_id가
     * products.id를 참조한다.
     *
     * 예:
     *
     * $productionRecord->product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 실제 작업자
     *
     * production_records.worker_id가
     * users.id를 참조한다.
     *
     * 시스템 입력자가 아니라
     * 실제 작업을 수행한 직원을 조회한다.
     *
     * 예:
     *
     * $productionRecord->worker
     */
    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * 최초 입력자
     *
     * production_records.created_by가
     * users.id를 참조한다.
     *
     * 실제 작업자와 관계없이 해당 기록을
     * 시스템에 최초 등록한 사용자를 조회한다.
     *
     * 예:
     *
     * $productionRecord->creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 마지막 수정자
     *
     * production_records.updated_by가
     * users.id를 참조한다.
     *
     * 기록이 수정된 경우 마지막으로 수정한 사용자를 조회한다.
     * 수정된 적이 없다면 관계 결과는 NULL이다.
     *
     * 예:
     *
     * $productionRecord->updater
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}