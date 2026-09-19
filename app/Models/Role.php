<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     * 대량 할당 가능한 속성
     *
     * Role::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 역할 코드
         *
         * 프로그램 내부에서 역할을 식별하기 위한
         * 고유한 코드이다.
         *
         * 예)
         * staff              = 일반 직원
         * kitchen_head       = 주방 헤드 셰프
         * hall_manager       = 홀 매니저
         * operations_staff   = 운영진 일반 직원
         * operations_manager = 운영진 매니저
         *
         * 화면에 표시되는 역할명이 변경되더라도
         * 프로그램 내부에서는 code를 기준으로 역할을 판단한다.
         */
        'code',

        /**
         * 역할 표시명
         *
         * 사용자 또는 관리자 화면에 표시되는
         * 사람이 읽기 위한 역할 이름이다.
         *
         * 예)
         * 일반 직원
         * 헤드 셰프
         * 홀 매니저
         * 운영진
         * 운영 매니저
         */
        'name',

        /**
         * 역할 설명
         *
         * 해당 역할이 어떤 목적으로 사용되는지
         * 상세하게 설명하기 위한 값이다.
         *
         * 역할의 종류가 많아졌을 때 각 역할의 용도를
         * 쉽게 구분할 수 있도록 사용한다.
         */
        'description',

        /**
         * 역할 사용 여부
         *
         * true(1)  = 현재 사용할 수 있는 역할
         * false(0) = 현재 사용하지 않는 역할
         *
         * 더 이상 사용하지 않는 역할이라도 기존 사용자와의
         * 관계를 보존하기 위해 바로 삭제하지 않고 상태로 관리한다.
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
             * 역할 사용 여부
             *
             * DB의 1/0 값을 PHP의 true/false 값으로
             * 자동 변환한다.
             */
            'is_active' => 'boolean',
        ];
    }

    /**
     * 해당 역할을 사용하는 사용자 목록
     *
     * 하나의 역할은 여러 명의 사용자에게 부여될 수 있으므로
     * Role과 User는 일대다(One-to-Many) 관계를 가진다.
     *
     * users.role_id가 roles.id를 참조한다.
     *
     * 예)
     * $role->users
     *
     * 위와 같이 사용하면 해당 역할을 가지고 있는
     * 사용자 목록을 조회할 수 있다.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * 역할에 부여된 권한 목록
     *
     * 하나의 역할은 여러 권한을 가질 수 있고
     * 하나의 권한 역시 여러 역할에 부여될 수 있으므로
     * Role과 Permission은 다대다(Many-to-Many) 관계를 가진다.
     *
     * role_permissions 테이블이 두 테이블을 연결하며
     * role_id와 permission_id를 통해 관계를 구성한다.
     *
     * 예)
     * kitchen_head
     * → production.view
     * → production.create
     * → production.proxy_create
     * → waste.view
     * → waste.proxy_create
     *
     * 예)
     * $role->permissions
     *
     * 위와 같이 사용하면 해당 역할에 부여된
     * 모든 기능 권한을 조회할 수 있다.
     */
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions'
        );
    }
}