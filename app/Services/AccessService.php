<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessService
{
    /**
     * 사용자가 특정 기능 권한을 가지고 있는지 검사합니다.
     *
     * User의 Role에 연결된 Permission을 확인하며,
     * 권한이 없는 경우 즉시 HTTP 403 응답을 발생시킵니다.
     *
     * Vue에서 메뉴나 버튼을 숨기는 것은
     * 사용자 화면을 위한 처리일 뿐이므로,
     * 실제 API 요청에서도 반드시 서버가 권한을 검사해야 합니다.
     *
     * 예:
     * $this->accessService->requirePermission(
     *     $user,
     *     'production.create'
     * );
     *
     * 주의:
     * 이 메서드는 "해당 기능을 사용할 수 있는가"만 확인합니다.
     *
     * 실제로 어느 점포 또는 어느 부서의 데이터에
     * 접근할 수 있는지는 Scope 또는 아래의
     * 범위 검증 메서드에서 별도로 처리합니다.
     */
    public function requirePermission(User $user, string $permission): void
    {
        abort_unless(
            $user->hasPermission($permission),
            403,
            '이 기능을 사용할 권한이 없습니다.'
        );
    }

    /**
     * 점포 + 부서 기준의 조회 범위를 적용합니다.
     *
     * 일반적인 Eloquent Query Builder에
     * 사용자의 점포 및 부서 접근 조건을 추가합니다.
     *
     * 접근 범위:
     *
     * super_admin
     * - 전체 점포
     * - 전체 부서
     *
     * 본사 계정
     * - 전체 점포
     * - 전체 부서
     *
     * 일반 점포 직원
     * - 자신의 점포
     * - 자신의 부서
     *
     * 점포 직원인데 store_id가 NULL인 비정상 계정
     * - 조회 불가
     *
     * $department가 false이면
     * 부서 조건을 적용하지 않고 점포만 제한합니다.
     *
     * 예:
     * $this->accessService->scopeStoreDepartment(
     *     Product::query(),
     *     $user
     * );
     *
     * 이 메서드는 Permission 자체를 검사하지 않으므로,
     * 필요한 기능 권한은 requirePermission()을 통해
     * 별도로 확인해야 합니다.
     */
    public function scopeStoreDepartment(
        Builder $query,
        User $user,
        bool $department = true
    ): Builder {
        /**
         * 최고 관리자 또는 본사 계정은
         * 점포 및 부서 조회 제한을 적용하지 않습니다.
         */
        if (
            $user->role?->code === 'super_admin'
            || $user->isHeadOffice()
        ) {
            return $query;
        }

        /**
         * 본사가 아닌 점포 직원은
         * 반드시 소속 점포가 존재해야 합니다.
         *
         * store_id가 없는 비정상 계정은
         * 어떤 데이터도 조회하지 못하도록 차단합니다.
         */
        if ($user->store_id === null) {
            return $query->whereRaw('1 = 0');
        }

        /**
         * 자신의 점포 데이터로 조회 범위를 제한합니다.
         */
        $query->where('store_id', $user->store_id);

        /**
         * 부서 제한이 필요한 경우
         * 자신의 부서 데이터로 추가 제한합니다.
         */
        if ($department) {
            $query->where('department', $user->department);
        }

        return $query;
    }

    /**
     * 점포 기준의 조회 범위를 적용합니다.
     *
     * 부서 구분이 필요하지 않은 데이터에서 사용합니다.
     *
     * 접근 범위:
     *
     * super_admin
     * - 전체 점포
     *
     * 본사 계정
     * - 전체 점포
     *
     * 일반 점포 직원
     * - 자신의 점포
     *
     * 점포 직원인데 store_id가 NULL인 비정상 계정
     * - 조회 불가
     *
     * 예:
     * $this->accessService->scopeStore(
     *     Product::query(),
     *     $user
     * );
     *
     * 이 메서드 역시 Permission 자체를 검사하지 않으므로
     * 기능 권한은 requirePermission()으로 별도 확인해야 합니다.
     */
    public function scopeStore(Builder $query, User $user): Builder
    {
        /**
         * 최고 관리자 또는 본사 계정은
         * 점포 조회 제한을 적용하지 않습니다.
         */
        if (
            $user->role?->code === 'super_admin'
            || $user->isHeadOffice()
        ) {
            return $query;
        }

        /**
         * 일반 점포 직원은 자신의 점포만 조회합니다.
         *
         * store_id가 없는 경우에는
         * 어떤 데이터도 조회하지 못하도록 차단합니다.
         */
        return $user->store_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('store_id', $user->store_id);
    }

    /**
     * 점포 업무 데이터의 생성/수정 대상 범위를 검증합니다.
     *
     * 조회 범위를 제한하는 scope 메서드와 달리,
     * 실제 데이터를 생성하거나 수정하려는 사용자가
     * 해당 점포 및 부서를 관리할 수 있는지 검사합니다.
     *
     * super_admin
     * - 모든 점포 및 부서 허용
     *
     * 본사 계정
     * - 점포 업무 데이터 직접 수정 불가
     *
     * 일반 점포 직원
     * - 자신의 점포만 허용
     * - department가 전달된 경우 자신의 부서만 허용
     *
     * 검증에 실패하면 HTTP 403 응답을 발생시킵니다.
     *
     * 예:
     * $this->accessService->assertStoreDepartment(
     *     $user,
     *     $storeId,
     *     $department
     * );
     *
     * 주의:
     * 이 메서드는 점포 및 부서 범위만 검사합니다.
     *
     * production.create 등의 실제 기능 권한은
     * requirePermission()으로 별도 검사해야 합니다.
     */
    public function assertStoreDepartment(
        User $user,
        ?int $storeId,
        ?string $department = null
    ): void {
        /**
         * 최고 시스템 관리자는
         * 점포 및 부서 제한을 적용하지 않습니다.
         */
        if ($user->role?->code === 'super_admin') {
            return;
        }

        /**
         * 본사 계정은 전체 점포 데이터를 조회할 수 있어도
         * 점포 업무 데이터를 직접 수정할 수는 없습니다.
         */
        if ($user->isHeadOffice()) {
            abort(
                403,
                '본사 계정은 점포 업무 데이터를 직접 수정할 수 없습니다.'
            );
        }

        /**
         * 자신의 소속 점포와 대상 점포가
         * 일치하는지 확인합니다.
         */
        abort_unless(
            $user->store_id === $storeId,
            403,
            '다른 점포의 데이터는 수정할 수 없습니다.'
        );

        /**
         * 대상 부서가 지정된 경우
         * 자신의 소속 부서와 일치하는지 확인합니다.
         */
        if ($department !== null) {
            abort_unless(
                $user->department === $department,
                403,
                '다른 부서의 데이터는 수정할 수 없습니다.'
            );
        }
    }
}