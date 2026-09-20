<?php

namespace App\Services\Scopes;

use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProductionRecordScope
{
    /**
     * 사용자가 조회할 수 있는 생산·폐기·로스 기록 범위를 적용한다.
     *
     * production.view 권한을 먼저 확인한 뒤
     * 사용자의 역할과 소속에 따라 조회 가능한 범위를 제한한다.
     *
     * Vue에서 데이터를 숨기는 것과 관계없이
     * 실제 DB 조회 단계에서 접근 범위를 제한하여
     * 다른 점포 또는 다른 부서의 기록이 노출되지 않도록 한다.
     */
    public function apply(User $user): Builder
    {
        $query = ProductionRecord::query();

        /**
         * 생산·폐기·로스 조회 권한이 없는 경우
         * 어떤 기록도 조회할 수 없도록 한다.
         *
         * 존재하지 않는 ID 조건을 사용하지 않고
         * 항상 거짓이 되는 조건을 적용한다.
         */
        if (! $user->hasPermission('production.view')) {
            return $query->whereRaw('1 = 0');
        }

        /**
         * 슈퍼어드민은 모든 점포와 모든 부서의
         * 생산·폐기·로스 기록을 조회할 수 있다.
         */
        if ($user->role?->code === 'super_admin') {
            return $query;
        }

        /**
         * 본사 직원과 본사 관리자는
         * 전체 점포의 생산·폐기·로스 기록을 조회할 수 있다.
         *
         * 본사 여부는 store_id가 NULL인지 여부가 아니라
         * department가 head_office인지 확인하여 판단한다.
         */
        if (
            $user->isHeadOffice()
            && in_array(
                $user->role?->code,
                ['head_office_staff', 'head_office_manager'],
                true
            )
        ) {
            return $query;
        }

        /**
         * 점포 소속 직원은 반드시 store_id가 존재해야 한다.
         *
         * 점포가 없는 비정상적인 계정 상태라면
         * 생산 기록을 조회하지 못하도록 차단한다.
         */
        if ($user->store_id === null) {
            return $query->whereRaw('1 = 0');
        }

        /**
         * 헤드셰프는 자기 점포의 주방 기록만 조회한다.
         */
        if ($user->role?->code === 'kitchen_head') {
            return $query
                ->where('store_id', $user->store_id)
                ->where('department', 'kitchen');
        }

        /**
         * 홀매니저는 자기 점포의 홀 기록만 조회한다.
         */
        if ($user->role?->code === 'hall_manager') {
            return $query
                ->where('store_id', $user->store_id)
                ->where('department', 'hall');
        }

        /**
         * 일반 직원은 자기 점포와 자기 부서의
         * 생산·폐기·로스 기록만 조회한다.
         *
         * 예:
         *
         * 무역점 주방 직원
         * → 무역점 + kitchen 기록만 조회
         *
         * 더현대서울점 홀 직원
         * → 더현대서울점 + hall 기록만 조회
         */
        if ($user->role?->code === 'staff') {
            return $query
                ->where('store_id', $user->store_id)
                ->where('department', $user->department);
        }

        /**
         * 위에서 명시적으로 허용하지 않은 역할은
         * 기본적으로 생산 기록을 조회할 수 없다.
         *
         * 새로운 역할이 추가되더라도 자동으로 접근 권한이
         * 열리지 않도록 기본 거부 방식으로 처리한다.
         */
        return $query->whereRaw('1 = 0');
    }
}