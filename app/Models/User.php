<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /**
     * Laravel 기본 사용자 기능 사용
     *
     * HasFactory는 테스트용 사용자 데이터 생성 등에 사용하고,
     * Notifiable은 알림 기능을 사용할 수 있도록 한다.
     *
     * SoftDeletes는 사용자를 실제 DB에서 삭제하지 않고
     * deleted_at 값을 이용하여 삭제 상태로 관리한다.
     *
     * 직원이 퇴사하거나 계정이 삭제 처리되더라도
     * 과거 생산, 폐기, 스케줄 및 변경 이력을
     * 계속 추적할 수 있도록 사용자 데이터를 보존한다.
     *
     * @use HasFactory<UserFactory>
     */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * 대량 할당 가능한 속성
     *
     * User::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 로그인 아이디
         *
         * 사용자가 Till White 시스템에 로그인할 때 사용하는
         * 고유한 아이디이다.
         *
         * users 테이블에서 unique 제약조건으로
         * 동일한 아이디의 중복 등록을 방지한다.
         */
        'login_id',

        /**
         * 사용자 이름
         *
         * 시스템 화면에 표시되는 실제 직원 이름이다.
         *
         * 생산자, 폐기 작업자, 입력자, 수정자 등의
         * 사용자 정보를 표시할 때 사용한다.
         */
        'name',

        /**
         * 로그인 비밀번호
         *
         * 사용자의 로그인 인증에 사용한다.
         *
         * casts의 hashed 설정을 통해 비밀번호 원문이 아닌
         * 해시 처리된 값을 DB에 저장한다.
         */
        'password',

        /**
         * 현재 소속 점포 ID
         *
         * stores 테이블의 id를 참조한다.
         *
         * 사용자가 현재 어느 점포에 소속되어 있는지 나타내며
         * 기본적인 점포 접근 범위를 판단할 때 사용한다.
         *
         * 과거 업무 기록은 사용자의 현재 store_id가 아니라
         * 각 업무 기록에 저장된 store_id를 기준으로 유지한다.
         */
        'store_id',

        /**
         * 현재 담당 부서
         *
         * 사용자가 현재 어느 업무 부서에 소속되어 있는지 나타낸다.
         *
         * 기본값 종류)
         * kitchen    = 주방
         * hall       = 홀
         * operations = 운영진 / 운영관리
         *
         * 역할(role)과 부서(department)는 서로 다른 개념이다.
         */
        'department',

        /**
         * 사용자 역할 ID
         *
         * roles 테이블의 id를 참조한다.
         *
         * 사용자의 역할을 users 테이블에 문자열로 직접 저장하지 않고
         * 별도의 roles 테이블과 연결하여 관리한다.
         *
         * 실제 기능 사용 권한은 역할에 연결된
         * permissions를 통해 판단할 수 있도록 한다.
         */
        'role_id',

        /**
         * 재직 상태
         *
         * true(1)  = 현재 재직중
         * false(0) = 퇴사
         *
         * 퇴사했다고 사용자 데이터를 삭제하지 않고
         * 과거 업무 기록을 유지하기 위해 별도로 관리한다.
         */
        'is_employed',

        /**
         * 계정 사용 가능 여부
         *
         * true(1)  = 로그인 및 시스템 사용 가능
         * false(0) = 계정 사용 중지
         *
         * 재직 여부와 계정 사용 여부는 서로 다르므로
         * is_employed와 별도로 관리한다.
         *
         * 예)
         * 재직중이지만 계정 정지
         * is_employed = true
         * is_active = false
         */
        'is_active',

        /**
         * 입사일
         *
         * 직원이 실제로 입사한 날짜를 저장한다.
         *
         * 정확한 입사일을 알 수 없는 기존 직원의 경우
         * null 값을 사용할 수 있다.
         */
        'hired_at',

        /**
         * 퇴사일
         *
         * 직원이 실제로 퇴사한 날짜를 저장한다.
         *
         * 재직중인 직원은 null이며
         * 퇴사한 경우 실제 퇴사 날짜를 저장한다.
         */
        'resigned_at',

        /**
         * 마지막 로그인 일시
         *
         * 사용자가 마지막으로 정상 로그인한
         * 날짜와 시간을 저장한다.
         *
         * 아직 로그인한 적이 없는 사용자는
         * null 값을 가진다.
         */
        'last_login_at',
    ];

    /**
     * 외부에 노출하지 않을 속성
     *
     * 사용자 모델을 JSON 또는 API 응답으로 변환할 때
     * 보안상 외부에 노출하면 안 되는 값을 숨긴다.
     */
    protected $hidden = [

        /**
         * 로그인 비밀번호
         *
         * 해시된 비밀번호 역시 외부 API 응답이나
         * JSON 데이터에 포함되지 않도록 숨긴다.
         */
        'password',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회하거나 모델에 값을 저장할 때
     * 각 컬럼을 적절한 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 로그인 비밀번호
             *
             * 비밀번호가 평문으로 입력되면 Laravel의
             * hashed cast를 이용하여 자동으로 해시 처리한다.
             */
            'password' => 'hashed',

            /**
             * 재직 상태
             *
             * DB의 1/0 값을 PHP의 true/false 값으로
             * 자동 변환한다.
             */
            'is_employed' => 'boolean',

            /**
             * 계정 사용 가능 여부
             *
             * DB의 1/0 값을 PHP의 true/false 값으로
             * 자동 변환한다.
             */
            'is_active' => 'boolean',

            /**
             * 입사일
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환한다.
             */
            'hired_at' => 'date',

            /**
             * 퇴사일
             *
             * DB의 date 값을 Laravel 날짜 객체로 변환한다.
             *
             * 재직중인 직원은 null 값이 그대로 유지된다.
             */
            'resigned_at' => 'date',

            /**
             * 마지막 로그인 일시
             *
             * 날짜뿐만 아니라 로그인 시간까지 필요하므로
             * datetime 타입으로 변환한다.
             */
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * 사용자가 현재 소속된 점포
     *
     * 하나의 사용자는 하나의 현재 소속 점포를 가지므로
     * User와 Store는 다대일(Many-to-One) 관계를 가진다.
     *
     * users.store_id가 stores.id를 참조한다.
     *
     * 예)
     * $user->store
     *
     * 위와 같이 사용하면 사용자의 현재 소속 점포를
     * 조회할 수 있다.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 사용자의 역할
     *
     * 하나의 사용자는 하나의 역할을 가지므로
     * User와 Role은 다대일(Many-to-One) 관계를 가진다.
     *
     * users.role_id가 roles.id를 참조한다.
     *
     * 실제 생산, 폐기, 스케줄 등의 기능 권한은
     * 해당 역할에 연결된 permissions를 통해 판단한다.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * 사용자의 근무 스케줄 목록
     *
     * 한 명의 사용자는 여러 날짜와 시간에 걸쳐
     * 여러 개의 근무 스케줄을 가질 수 있다.
     *
     * work_schedules.user_id가 users.id를 참조한다.
     *
     * 예)
     * $user->workSchedules
     *
     * 생산 및 폐기 입력 시 해당 직원이 해당 날짜에
     * 근무 대상이었는지 확인하는 데 사용할 수 있다.
     */
    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }
}