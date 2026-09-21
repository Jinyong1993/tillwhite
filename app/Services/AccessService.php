<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessService
{
    /**
     * 사용자의 기능 권한을 검사한다.
     *
     * 화면에서 버튼을 숨기는 것과 별개로 모든 API에서 다시 호출하여
     * 서버가 최종 권한 판단 주체가 되도록 한다.
     */
    public function requirePermission(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403, '이 기능을 사용할 권한이 없습니다.');
    }

    /**
     * 일반적인 점포/부서 데이터 조회 범위를 적용한다.
     *
     * 본사와 최고 관리자는 전체 점포를 조회하고,
     * 점포 직원은 자신의 점포와 부서 범위로 제한한다.
     */
    public function scopeStoreDepartment(Builder $query, User $user, bool $department = true): Builder
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return $query;
        }

        if ($user->store_id === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('store_id', $user->store_id);

        if ($department) {
            $query->where('department', $user->department);
        }

        return $query;
    }

    /**
     * 점포 단위 데이터 범위를 적용한다.
     */
    public function scopeStore(Builder $query, User $user): Builder
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return $query;
        }

        return $user->store_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('store_id', $user->store_id);
    }

    /**
     * 점포 직원의 생성/수정 대상 점포와 부서를 검증한다.
     */
    public function assertStoreDepartment(User $user, ?int $storeId, ?string $department = null): void
    {
        if ($user->role?->code === 'super_admin') {
            return;
        }

        if ($user->isHeadOffice()) {
            abort(403, '본사 계정은 점포 업무 데이터를 직접 수정할 수 없습니다.');
        }

        abort_unless($user->store_id === $storeId, 403, '다른 점포의 데이터는 수정할 수 없습니다.');

        if ($department !== null) {
            abort_unless($user->department === $department, 403, '다른 부서의 데이터는 수정할 수 없습니다.');
        }
    }
}
