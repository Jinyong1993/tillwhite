<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteRecord extends Model
{
    /**
     * Soft Delete 사용
     *
     * 폐기 기록을 실제 DB에서 바로 삭제하지 않고
     * deleted_at 값을 이용하여 삭제 상태로 관리한다.
     *
     * 잘못 입력된 폐기 기록을 삭제하더라도 과거 기록 및
     * 변경 이력을 추적할 수 있도록 데이터를 보존한다.
     */
    use SoftDeletes;

    /**
     * 대량 할당 가능한 속성
     *
     * WasteRecord::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 실제 폐기 업무 날짜
         *
         * 폐기 기록이 어느 날짜의 업무에 해당하는지 저장한다.
         *
         * created_at은 시스템에 실제 입력한 시간이므로
         * 폐기 업무 날짜를 의미하는 work_date와 구분하여 사용한다.
         *
         * 일별, 요일별, 주별, 월별 폐기 통계의
         * 기준 날짜로 사용한다.
         */
        'work_date',

        /**
         * 폐기 발생 점포 ID
         *
         * stores 테이블의 id를 참조한다.
         *
         * 해당 폐기가 어느 점포에서 발생했는지 저장한다.
         *
         * 직원이 이후 다른 점포로 이동하더라도 과거 폐기 기록의
         * 점포 정보가 변경되지 않도록 기록 자체에 저장한다.
         */
        'store_id',

        /**
         * 폐기 발생 부서
         *
         * 해당 폐기 기록이 발생한 당시의 부서를 저장한다.
         *
         * 기본값 종류)
         * kitchen    = 주방
         * hall       = 홀
         * operations = 운영진 / 운영관리
         *
         * 사용자의 현재 department와 별도로 저장하여
         * 과거 폐기 당시의 부서 정보를 보존한다.
         */
        'department',

        /**
         * 폐기 제품 ID
         *
         * products 테이블의 id를 참조한다.
         *
         * 어떤 제품이 폐기되었는지 식별하며
         * 제품명 자체를 폐기 기록에 중복 저장하지 않는다.
         */
        'product_id',

        /**
         * 실제 폐기 작업자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 시스템에 기록을 입력한 사람이 아니라
         * 실제 폐기 업무를 담당한 직원을 의미한다.
         *
         * 예)
         * 직원 A가 소금빵 5개 폐기
         * 헤드 셰프 B가 대신 시스템에 입력
         *
         * worker_id = 직원 A
         * created_by = 헤드 셰프 B
         */
        'worker_id',

        /**
         * 폐기 수량
         *
         * 해당 제품이 실제로 폐기된 수량을 저장한다.
         *
         * 현재 완제품 폐기량은 개수 단위의 정수로 관리한다.
         *
         * 0보다 큰 값인지 등의 업무 규칙은
         * Laravel Form Request에서 추가로 검증한다.
         */
        'quantity',

        /**
         * 폐기 사유
         *
         * 제품이 폐기된 대표적인 원인을 저장한다.
         *
         * 기본값 종류)
         * unsold     = 미판매
         * damaged   = 파손 또는 제품 상태 불량
         * expired   = 유통기한 또는 판매 가능 시간 경과
         * production = 생산 과정에서 발생한 폐기
         * other      = 기타
         *
         * 허용되는 사유 값은 Laravel Form Request에서
         * 엄격하게 검증하도록 한다.
         *
         * 폐기 사유를 기록하지 않는 경우를 허용하기 위해
         * DB에서는 null 값을 사용할 수 있다.
         */
        'reason',

        /**
         * 폐기 상세 메모
         *
         * reason만으로 설명하기 어려운 폐기 상황을
         * 추가로 기록하기 위한 자유 입력 내용이다.
         *
         * 예)
         * 오븐 온도 문제로 제품 상태 불량
         * 진열 중 제품 파손
         * 행사 종료 후 잔여 제품 폐기
         *
         * reason이 other인 경우에는 향후 Form Request에서
         * memo 입력을 필수로 요구할 수 있다.
         *
         * 별도의 설명이 필요하지 않은 경우
         * null 값을 사용할 수 있다.
         */
        'memo',

        /**
         * 최초 입력자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 해당 폐기 기록을 시스템에 실제로 등록한
         * 사용자를 저장한다.
         *
         * worker_id와 created_by가 같으면 본인 입력이며,
         * 서로 다르면 다른 직원의 폐기 기록을 대신 입력한
         * 대리 입력으로 판단할 수 있다.
         */
        'created_by',

        /**
         * 마지막 수정자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 폐기 기록이 수정되었을 때 마지막으로 수정한
         * 사용자를 저장한다.
         *
         * 한 번도 수정되지 않은 폐기 기록은
         * null 값을 가질 수 있다.
         */
        'updated_by',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회한 값을 애플리케이션에서 사용할 때
     * 필요한 컬럼을 적절한 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 실제 폐기 업무 날짜
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환하여
             * 날짜 비교 및 통계 처리를 쉽게 할 수 있도록 한다.
             */
            'work_date' => 'date',

            /**
             * 폐기 수량
             *
             * 폐기량을 항상 정수로 사용할 수 있도록
             * integer 타입으로 변환한다.
             */
            'quantity' => 'integer',
        ];
    }

    /**
     * 폐기 기록이 발생한 점포
     *
     * 하나의 폐기 기록은 하나의 점포에 속하므로
     * WasteRecord와 Store는 다대일 관계를 가진다.
     *
     * waste_records.store_id가 stores.id를 참조한다.
     *
     * 예)
     * $wasteRecord->store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 폐기된 제품
     *
     * 하나의 폐기 기록은 하나의 제품에 속하므로
     * WasteRecord와 Product는 다대일 관계를 가진다.
     *
     * waste_records.product_id가 products.id를 참조한다.
     *
     * 예)
     * $wasteRecord->product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 실제 폐기 작업자
     *
     * waste_records.worker_id가 users.id를 참조한다.
     *
     * 기록을 시스템에 입력한 사람이 아니라
     * 실제 폐기 업무를 담당한 직원을 조회하는 관계이다.
     *
     * 예)
     * $wasteRecord->worker
     */
    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * 최초 입력자
     *
     * waste_records.created_by가 users.id를 참조한다.
     *
     * 실제 폐기 작업자와 관계없이 해당 기록을
     * 시스템에 최초 등록한 사용자를 조회한다.
     *
     * 예)
     * $wasteRecord->creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 마지막 수정자
     *
     * waste_records.updated_by가 users.id를 참조한다.
     *
     * 기록이 수정된 경우 마지막으로 수정한 사용자를 조회한다.
     * 수정 이력이 없다면 관계 결과는 null이 될 수 있다.
     *
     * 예)
     * $wasteRecord->updater
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}