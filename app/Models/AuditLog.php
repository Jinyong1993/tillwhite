<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * Laravel 기본 timestamps 자동 관리 비활성화
     *
     * audit_logs 테이블은 일반 테이블과 달리
     * updated_at 컬럼을 사용하지 않는다.
     *
     * 감사 기록은 생성된 이후 수정하는 데이터가 아니라
     * 발생한 이력을 그대로 보존하는 것을 목적으로 하므로
     * created_at만 별도로 저장한다.
     */
    public $timestamps = false;

    /**
     * 대량 할당 가능한 속성
     *
     * AuditLog::create() 등을 사용하여 감사 기록을 생성할 때
     * 한 번에 입력할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 작업 수행 사용자 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 어떤 사용자가 해당 데이터 변경 작업을
         * 수행했는지 추적하기 위해 저장한다.
         *
         * 예)
         * 생산 기록을 등록한 직원
         * 폐기 기록을 수정한 헤드 셰프
         * 특정 기록을 삭제 처리한 관리자
         */
        'user_id',

        /**
         * 작업 발생 점포 ID
         *
         * stores 테이블의 id를 참조한다.
         *
         * 해당 변경 작업이 어느 점포의 데이터와
         * 관련되어 있는지 기록한다.
         *
         * 점포와 직접 관련되지 않는 시스템 작업도
         * 기록할 수 있도록 null 값을 허용한다.
         */
        'store_id',

        /**
         * 수행된 작업 종류
         *
         * 데이터에 어떤 작업이 발생했는지 나타낸다.
         *
         * 기본값 종류)
         * create  = 데이터 생성
         * update  = 데이터 수정
         * delete  = 데이터 삭제 처리
         * restore = 삭제된 데이터 복구
         *
         * 허용되는 action 값은 향후 서비스 또는
         * 별도의 검증 로직에서 제한할 수 있다.
         */
        'action',

        /**
         * 변경 대상 테이블명
         *
         * 어떤 테이블의 데이터가 변경되었는지 저장한다.
         *
         * 예)
         * production_records
         * waste_records
         * users
         * products
         *
         * 여러 종류의 업무 데이터를 하나의 audit_logs
         * 테이블에서 통합 관리하기 위해 사용한다.
         */
        'table_name',

        /**
         * 변경 대상 레코드 ID
         *
         * table_name으로 지정된 테이블에서 실제로
         * 변경된 데이터의 id 값을 저장한다.
         *
         * 예)
         * table_name = production_records
         * record_id = 15
         *
         * 위의 경우 production_records 테이블의
         * id 15번 기록에 대한 감사 로그를 의미한다.
         *
         * 여러 종류의 테이블을 참조할 수 있으므로
         * 특정 테이블에 대한 외래 키는 설정하지 않는다.
         */
        'record_id',

        /**
         * 변경 이전 데이터
         *
         * 수정 또는 삭제 작업이 발생하기 전의
         * 데이터 상태를 JSON 형태로 저장한다.
         *
         * 예)
         * 생산량 30개를 40개로 수정했다면
         * old_values에는 수정 전 quantity 30이 기록될 수 있다.
         *
         * 데이터 생성처럼 이전 값이 존재하지 않는 경우에는
         * null 값을 사용할 수 있다.
         */
        'old_values',

        /**
         * 변경 이후 데이터
         *
         * 생성 또는 수정 작업이 완료된 이후의
         * 데이터 상태를 JSON 형태로 저장한다.
         *
         * 예)
         * 생산량 30개를 40개로 수정했다면
         * new_values에는 수정 후 quantity 40이 기록될 수 있다.
         *
         * 삭제 작업처럼 변경 이후 값을 기록할 필요가 없는 경우
         * null 값을 사용할 수 있다.
         */
        'new_values',

        /**
         * 변경 사유
         *
         * 사용자가 데이터를 수정하거나 삭제한 이유를
         * 추가로 기록하기 위한 값이다.
         *
         * 예)
         * 생산 수량 오입력 수정
         * 직원 선택 오류로 작업자 변경
         * 중복 입력된 폐기 기록 삭제
         *
         * 변경 사유가 필요하지 않은 작업은
         * null 값을 사용할 수 있다.
         */
        'reason',

        /**
         * 감사 기록 생성 일시
         *
         * 해당 변경 작업이 언제 감사 로그에 기록되었는지
         * 날짜와 시간으로 저장한다.
         *
         * audit_logs 테이블에서 DB의 useCurrent() 설정을 통해
         * 기록 생성 시 현재 시간이 기본값으로 저장된다.
         */
        'created_at',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회한 감사 기록을 애플리케이션에서 사용할 때
     * JSON 및 날짜 데이터를 적절한 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 변경 이전 데이터
             *
             * DB의 JSON 데이터를 PHP 배열로 자동 변환하여
             * 변경 전 값을 쉽게 확인할 수 있도록 한다.
             */
            'old_values' => 'array',

            /**
             * 변경 이후 데이터
             *
             * DB의 JSON 데이터를 PHP 배열로 자동 변환하여
             * 변경 후 값을 쉽게 확인할 수 있도록 한다.
             */
            'new_values' => 'array',

            /**
             * 감사 기록 생성 일시
             *
             * DB의 timestamp 값을 Laravel 날짜/시간 객체로
             * 자동 변환한다.
             */
            'created_at' => 'datetime',
        ];
    }

    /**
     * 작업을 수행한 사용자
     *
     * audit_logs.user_id가 users.id를 참조한다.
     *
     * 어떤 사용자가 해당 변경 작업을 수행했는지
     * 조회하기 위한 관계이다.
     *
     * 예)
     * $auditLog->user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 작업과 관련된 점포
     *
     * audit_logs.store_id가 stores.id를 참조한다.
     *
     * 어떤 점포의 데이터에서 변경이 발생했는지
     * 조회하기 위한 관계이다.
     *
     * store_id는 nullable이므로 점포와 관련 없는
     * 감사 기록에서는 null이 될 수 있다.
     *
     * 예)
     * $auditLog->store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}