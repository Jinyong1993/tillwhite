<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /**
     * 대량 할당 가능한 속성
     *
     * Permission::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 권한 코드
         *
         * 프로그램 내부에서 특정 기능 권한을
         * 식별하기 위한 고유한 코드이다.
         *
         * "기능.행동" 형식으로 통일하여
         * 어떤 기능에 대한 어떤 권한인지 쉽게 구분한다.
         *
         * 예)
         * production.view
         * production.create
         * production.update
         * production.proxy_create
         * production.proxy_update
         *
         * waste.view
         * waste.create
         * waste.update
         *
         * schedule.view
         * schedule.manage
         *
         * 화면에 표시되는 권한명이 변경되더라도
         * 프로그램 내부에서는 code를 기준으로 권한을 판단한다.
         */
        'code',

        /**
         * 권한 표시명
         *
         * 관리자 화면이나 권한 설정 화면에서
         * 사람이 이해하기 쉽게 표시하는 권한 이름이다.
         *
         * 예)
         * 생산 기록 조회
         * 생산 기록 입력
         * 생산 기록 대리 입력
         * 폐기 기록 수정
         * 스케줄 관리
         */
        'name',

        /**
         * 권한 설명
         *
         * 해당 권한으로 어떤 작업을 수행할 수 있는지
         * 상세하게 설명하기 위한 값이다.
         *
         * 권한 종류가 많아졌을 때 권한명만으로 발생할 수 있는
         * 혼동을 줄이고 정확한 용도를 확인할 수 있도록 한다.
         *
         * DB에서는 nullable이므로 설명이 필요하지 않은 경우
         * null 값을 사용할 수 있다.
         */
        'description',

        /**
         * 권한 사용 여부
         *
         * true(1)  = 현재 사용하는 권한
         * false(0) = 현재 사용하지 않는 권한
         *
         * 특정 기능이 폐지되거나 일시적으로 사용되지 않더라도
         * 기존 역할과의 권한 연결 정보를 유지할 수 있도록
         * 데이터를 바로 삭제하지 않고 상태값으로 관리한다.
         */
        'is_active',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회한 값을 애플리케이션에서 사용할 때
     * 각 컬럼에 맞는 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 권한 사용 여부
             *
             * DB의 1/0 값을 PHP의 true/false 값으로
             * 자동 변환한다.
             */
            'is_active' => 'boolean',
        ];
    }

    /**
     * 해당 권한을 가지고 있는 역할 목록
     *
     * 하나의 권한은 여러 역할에 부여될 수 있고
     * 하나의 역할 역시 여러 권한을 가질 수 있으므로
     * Permission과 Role은 다대다(Many-to-Many) 관계를 가진다.
     *
     * role_permissions 테이블이 두 테이블을 연결하며
     * permission_id와 role_id를 통해 관계를 구성한다.
     *
     * 예)
     * production.proxy_create
     * → kitchen_head
     *
     * waste.proxy_create
     * → kitchen_head
     * → hall_manager
     *
     * 예)
     * $permission->roles
     *
     * 위와 같이 사용하면 해당 권한이 부여되어 있는
     * 모든 역할을 조회할 수 있다.
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions'
        );
    }
}