<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    /**
     * 대량 할당 가능한 속성
     *
     * WorkSchedule::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 근무 직원 ID
         *
         * users 테이블의 id를 참조한다.
         *
         * 해당 근무 스케줄이 어떤 직원의 스케줄인지
         * 식별하기 위해 사용한다.
         */
        'user_id',

        /**
         * 근무 점포 ID
         *
         * stores 테이블의 id를 참조한다.
         *
         * 사용자의 현재 소속 점포와 별도로
         * 해당 근무일에 실제 배정된 점포를 저장한다.
         *
         * 직원이 나중에 다른 점포로 이동하더라도
         * 과거 근무 점포 기록을 유지할 수 있다.
         */
        'store_id',

        /**
         * 근무 부서
         *
         * 해당 근무일에 직원이 근무하는 부서를 저장한다.
         *
         * 기본값 종류)
         * kitchen    = 주방
         * hall       = 홀
         * operations = 운영진 / 운영관리
         *
         * 사용자의 현재 department와 별도로 저장하여
         * 과거 근무 당시의 부서 정보를 보존한다.
         */
        'department',

        /**
         * 근무 날짜
         *
         * 직원이 실제로 근무하도록 배정된 업무 날짜이다.
         *
         * 생산 및 폐기 기록을 입력할 때 해당 직원이
         * 그 날짜에 근무 대상인지 검증하는 기준으로 사용한다.
         */
        'work_date',

        /**
         * 근무 시작 시간
         *
         * 해당 근무 스케줄의 예정 시작 시간을 저장한다.
         *
         * 정확한 시작 시간이 정해지지 않은 경우에는
         * null 값을 사용할 수 있다.
         */
        'start_time',

        /**
         * 근무 종료 시간
         *
         * 해당 근무 스케줄의 예정 종료 시간을 저장한다.
         *
         * 정확한 종료 시간이 정해지지 않은 경우에는
         * null 값을 사용할 수 있다.
         */
        'end_time',

        /**
         * 근무 상태
         *
         * 해당 근무 스케줄의 현재 상태를 나타낸다.
         *
         * 기본값 종류)
         * scheduled = 근무 예정
         * working   = 근무중
         * completed = 근무 완료
         * absent    = 결근
         * cancelled = 근무 취소
         *
         * 생산 및 폐기 입력 권한을 검증할 때는
         * 단순히 스케줄 존재 여부만 확인하지 않고
         * 이 상태값도 함께 확인할 수 있다.
         */
        'status',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회한 값을 애플리케이션에서 사용할 때
     * 날짜 및 시간 컬럼을 적절한 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 근무 날짜
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환하여
             * 날짜 비교 및 계산을 쉽게 처리할 수 있도록 한다.
             */
            'work_date' => 'date',

            /**
             * 근무 시작 시간
             *
             * DB에 저장된 시간을 시:분 형식으로 사용할 수 있도록
             * HH:mm 형식으로 변환한다.
             */
            'start_time' => 'datetime:H:i',

            /**
             * 근무 종료 시간
             *
             * DB에 저장된 시간을 시:분 형식으로 사용할 수 있도록
             * HH:mm 형식으로 변환한다.
             */
            'end_time' => 'datetime:H:i',
        ];
    }

    /**
     * 근무 스케줄의 직원
     *
     * 하나의 근무 스케줄은 한 명의 사용자에게 속하므로
     * WorkSchedule과 User는 다대일(Many-to-One) 관계를 가진다.
     *
     * work_schedules.user_id가 users.id를 참조한다.
     *
     * 예)
     * $workSchedule->user
     *
     * 위와 같이 사용하면 해당 스케줄의
     * 근무 직원을 조회할 수 있다.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 근무 스케줄의 점포
     *
     * 하나의 근무 스케줄은 하나의 근무 점포에 속하므로
     * WorkSchedule과 Store는 다대일(Many-to-One) 관계를 가진다.
     *
     * work_schedules.store_id가 stores.id를 참조한다.
     *
     * 예)
     * $workSchedule->store
     *
     * 위와 같이 사용하면 해당 스케줄이 배정된
     * 점포 정보를 조회할 수 있다.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}