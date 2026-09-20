<?php

namespace App\Models;

use Database\Factories\UserFactory;
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
     * 대량 할당 가능한 속성
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
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 속성 타입 변환
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
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 현재 직급/직책
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * 시스템 역할
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * 근무 스케줄
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * 특정 권한을 가지고 있는지 확인한다.
     *
     * 권한 자체만 확인하며,
     * 자기 점포/부서/전 점포 등의 접근 범위는
     * Policy 또는 Service에서 별도로 판단한다.
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
     * 재직 중인지 확인한다.
     */
    public function isEmployed(): bool
    {
        return $this->employment_status === 'active';
    }

    /**
     * 휴직 중인지 확인한다.
     */
    public function isOnLeave(): bool
    {
        return $this->employment_status === 'leave';
    }

    /**
     * 퇴사 상태인지 확인한다.
     */
    public function isResigned(): bool
    {
        return $this->employment_status === 'resigned';
    }

    /**
     * 본사 직원인지 확인한다.
     */
    public function isHeadOffice(): bool
    {
        return $this->department === 'head_office';
    }

    /**
     * 로그인 가능한 상태인지 확인한다.
     *
     * 활성화된 계정이며 현재 재직 상태인 경우만 로그인 가능하다.
     */
    public function canLogin(): bool
    {
        return $this->is_active
            && $this->employment_status === 'active';
    }
}