<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Till White 사용자 및 직원 정보
     *
     * User는 시스템 로그인 계정이면서
     * 실제 직원 정보를 나타내는 모델입니다.
     *
     * 사용자의 현재 점포, 부서, 직급, 시스템 역할,
     * 재직 상태 및 계정 상태 등을 함께 관리합니다.
     *
     * ------------------------------------------------------------
     * 주요 구분
     * ------------------------------------------------------------
     *
     * store
     * - 현재 소속 점포
     * - 본사 직원은 store_id가 NULL
     *
     * department
     * - 현재 소속 부서
     * - kitchen     : 주방
     * - hall        : 홀
     * - head_office : 본사
     *
     * position
     * - 회사 조직상의 직급
     * - 예: 사원, 주임, 대리, 과장, 부장 등
     *
     * role
     * - 시스템에서 사용하는 권한 묶음
     * - 예: staff, kitchen_head, hall_manager 등
     *
     * employment_status
     * - 직원의 현재 재직 상태
     * - active   : 재직
     * - leave    : 휴직
     * - resigned : 퇴사
     *
     * is_active
     * - 시스템 계정의 활성화 여부
     *
     * 따라서 재직 상태와 시스템 계정 활성 상태는
     * 서로 별개의 값으로 관리합니다.
     */

    /**
     * 대량 할당 가능한 속성
     *
     * employee_code       : 사번 및 로그인 ID
     * name                : 직원명
     * password            : 로그인 비밀번호
     * password_changed_at : 마지막 비밀번호 변경 시간
     * phone               : 연락처
     * birth_date          : 생년월일
     * store_id            : 현재 소속 점포
     * department          : 현재 소속 부서
     * position_id         : 현재 직급
     * role_id             : 시스템 역할
     * employment_status   : 재직 상태
     * hired_at            : 입사일
     * resigned_at         : 퇴사일
     * is_active           : 계정 활성화 여부
     * last_login_at       : 마지막 로그인 시간
     */
    protected $fillable = [
        'employee_code',
        'name',
        'password',
        'password_changed_at',
        'phone',
        'birth_date',
        'store_id',
        'department',
        'position_id',
        'role_id',
        'employment_status',
        'hired_at',
        'resigned_at',
        'is_active',
        'last_login_at',
    ];

    /**
     * 외부에 노출하지 않을 속성
     *
     * User 모델을 배열 또는 JSON으로 변환할 때
     * 비밀번호와 로그인 유지 토큰이
     * 응답 데이터에 포함되지 않도록 합니다.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 사용자 컬럼의 타입 변환 설정
     *
     * password            : 저장 시 자동 해시 처리
     * password_changed_at : 비밀번호 변경 시간을 datetime으로 변환
     * birth_date          : 생년월일을 date로 변환
     * is_active           : 계정 활성 상태를 boolean으로 변환
     * hired_at            : 입사일을 date로 변환
     * resigned_at         : 퇴사일을 date로 변환
     * last_login_at       : 마지막 로그인 시간을 datetime으로 변환
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'hired_at' => 'date',
            'resigned_at' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * 현재 소속 점포
     *
     * users.store_id를 기준으로
     * User와 Store를 연결합니다.
     *
     * 일반 점포 직원은 하나의 점포에 소속되며,
     * 본사 직원은 store_id가 NULL입니다.
     *
     * 주의:
     * store_id가 NULL이라는 사실만으로
     * 본사 직원 또는 전체 점포 접근 권한을
     * 부여해서는 안 됩니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 현재 직급
     *
     * users.position_id를 기준으로
     * User와 Position을 연결합니다.
     *
     * Position은 회사 조직상의 직급이며
     * 시스템 권한을 결정하는 Role과는 별개입니다.
     *
     * 예:
     * 사원, 주임, 대리, 과장, 차장, 부장 등
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * 시스템 역할
     *
     * users.role_id를 기준으로
     * User와 Role을 연결합니다.
     *
     * Role에는 여러 Permission이 연결되며,
     * 이를 통해 사용자가 어떤 시스템 기능을
     * 사용할 수 있는지 판단합니다.
     *
     * 예:
     * staff
     * kitchen_head
     * hall_manager
     * head_office_staff
     * head_office_manager
     * super_admin
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * 직원의 근무 스케줄 목록
     *
     * work_schedules.user_id를 기준으로
     * User와 WorkSchedule을 연결합니다.
     *
     * 하나의 직원은 날짜별로 여러 근무 스케줄을
     * 가질 수 있습니다.
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * 특정 기능 권한을 가지고 있는지 확인합니다.
     *
     * 현재 사용자의 Role에 연결된 Permission 중
     * 전달받은 권한 코드와 일치하면서
     * 활성화된 권한이 존재하는지 확인합니다.
     *
     * 예:
     * $user->hasPermission('production.view');
     * $user->hasPermission('schedule.manage');
     * $user->hasPermission('employee.manage');
     *
     * 이 메서드는 "어떤 기능을 사용할 수 있는가"만
     * 확인합니다.
     *
     * 실제로 어느 점포 또는 어느 부서의 데이터에
     * 접근할 수 있는지는 AccessService 및 Scope에서
     * 별도로 제한해야 합니다.
     */
    public function hasPermission(string $permissionCode): bool
    {
        if (! $this->role) {
            return false;
        }

        return $this->role
            ->permissions()
            ->where('code', $permissionCode)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * 현재 재직 중인지 확인합니다.
     *
     * employment_status가 active이면 true를 반환합니다.
     */
    public function isEmployed(): bool
    {
        return $this->employment_status === 'active';
    }

    /**
     * 현재 휴직 중인지 확인합니다.
     *
     * employment_status가 leave이면 true를 반환합니다.
     */
    public function isOnLeave(): bool
    {
        return $this->employment_status === 'leave';
    }

    /**
     * 퇴사 상태인지 확인합니다.
     *
     * employment_status가 resigned이면 true를 반환합니다.
     */
    public function isResigned(): bool
    {
        return $this->employment_status === 'resigned';
    }

    /**
     * 본사 소속 직원인지 확인합니다.
     *
     * department가 head_office인 경우
     * 본사 소속으로 판단합니다.
     *
     * store_id가 NULL인지 여부만으로
     * 본사 직원을 판단하지 않습니다.
     */
    public function isHeadOffice(): bool
    {
        return $this->department === 'head_office';
    }

    /**
     * 현재 로그인 가능한 상태인지 확인합니다.
     *
     * 다음 두 조건을 모두 만족해야 합니다.
     *
     * 1. is_active가 true인 활성 계정
     * 2. employment_status가 active인 재직 직원
     *
     * 따라서 휴직, 퇴사 또는 비활성화된 계정은
     * 로그인할 수 없습니다.
     */
    public function canLogin(): bool
    {
        return $this->is_active
            && $this->employment_status === 'active';
    }
}