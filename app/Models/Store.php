<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    /**
     * Soft Delete 사용
     *
     * 점포 데이터를 실제 DB에서 바로 삭제하지 않고
     * deleted_at 값을 이용하여 삭제 상태로 관리한다.
     *
     * 점포가 삭제 처리되더라도 해당 점포와 연결된
     * 과거 생산, 폐기, 스케줄 등의 기록을 보존하기 위해 사용한다.
     */
    use SoftDeletes;

    /**
     * 대량 할당 가능한 속성
     *
     * Store::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 점포 코드
         *
         * 실제 업무 및 시스템에서 점포를 식별하기 위한
         * 고유한 코드이다.
         *
         * 예)
         * 1 = 무역점
         * 2 = 다른 점포
         *
         * DB 관계에서 사용하는 id와는 별도로 관리한다.
         */
        'store_code',

        /**
         * 점포명
         *
         * 사용자 화면에 표시되는 실제 점포 이름이다.
         *
         * 예)
         * 무역점
         */
        'name',

        /**
         * 점포 운영 상태
         *
         * true(1)  = 현재 영업중
         * false(0) = 폐점
         *
         * 점포가 폐점하더라도 기존 업무 기록을 보존하기 위해
         * 점포 자체를 삭제하지 않고 상태값으로 관리한다.
         */
        'status',

        /**
         * 점포 영업 시작일
         *
         * 해당 점포가 실제로 영업을 시작한 날짜이다.
         *
         * 정확한 오픈일을 알 수 없는 기존 점포의 경우에는
         * null 값을 사용할 수 있다.
         */
        'opened_at',

        /**
         * 점포 폐점일
         *
         * 해당 점포가 실제로 폐점한 날짜이다.
         *
         * 현재 영업중인 점포는 null이며,
         * 폐점한 경우 실제 폐점 날짜를 저장한다.
         */
        'closed_at',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회하거나 모델에서 사용할 때
     * 각 컬럼을 적절한 PHP/Laravel 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 점포 운영 상태
             *
             * DB의 1/0 값을 PHP의 true/false 값으로
             * 자동 변환하여 사용할 수 있도록 한다.
             */
            'status' => 'boolean',

            /**
             * 점포 영업 시작일
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환하여
             * 날짜 계산 및 형식 변환을 쉽게 처리할 수 있도록 한다.
             */
            'opened_at' => 'date',

            /**
             * 점포 폐점일
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환한다.
             *
             * 영업중인 점포의 경우 null 값이 그대로 유지된다.
             */
            'closed_at' => 'date',
        ];
    }

    /**
     * 점포에 소속된 사용자 목록
     *
     * 하나의 점포에는 여러 명의 사용자가 소속될 수 있으므로
     * Store와 User는 일대다(One-to-Many) 관계를 가진다.
     *
     * users 테이블의 store_id가
     * stores 테이블의 id를 참조한다.
     *
     * 예)
     * $store->users
     *
     * 위와 같이 사용하면 해당 점포에 현재 연결되어 있는
     * 사용자 목록을 조회할 수 있다.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}