<?php

namespace App\Services\Scopes;

use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProductionRecordScope
{
    /**
     * 사용자가 조회할 수 있는 생산·폐기·로스 기록 범위를 적용합니다.
     *
     * 먼저 production.view 권한을 확인한 뒤,
     * 사용자의 Role, 점포, 부서를 기준으로
     * 실제 DB 조회 범위를 제한합니다.
     *
     * Vue에서 메뉴나 데이터를 숨기는 것은
     * 사용자 화면을 위한 처리일 뿐이며,
     * 실제 데이터 접근 제한은 서버에서 처리합니다.
     *
     * 접근 범위:
     *
     * super_admin
     * - 모든 점포
     * - 모든 부서
     *
     * head_office_staff
     * - 모든 점포
     * - 모든 부서
     * - 조회만 가능하며 수정 권한은 별도로 판단
     *
     * head_office_manager
     * - 모든 점포
     * - 모든 부서
     * - 조회만 가능하며 수정 권한은 별도로 판단
     *
     * kitchen_head
     * - 자신의 점포
     * - kitchen 부서
     *
     * hall_manager
     * - 자신의 점포
     * - hall 부서
     *
     * staff
     * - 자신의 점포
     * - 자신의 부서
     *
     * 그 외 역할
     * - 조회 불가
     *
     * 주의:
     * 이 Scope는 "조회 가능한 데이터 범위"를 결정합니다.
     *
     * production.create
     * production.update
     * production.delete
     *
     * 등의 작업 권한은 각각 별도로 검사해야 합니다.
     */
    public function apply(User $user): Builder
    {
        $query = ProductionRecord::query();

        /**
         * 생산·폐기·로스 기록 조회 권한 확인
         *
         * production.view 권한이 없다면
         * 어떤 기록도 조회할 수 없습니다.
         *
         * 항상 거짓이 되는 SQL 조건을 적용하여
         * 빈 조회 결과를 반환합니다.
         */
        if (! $user->hasPermission('production.view')) {
            return $query->whereRaw('1 = 0');
        }

        /**
         * 최고 시스템 관리자
         *
         * super_admin은 점포와 부서 제한 없이
         * 모든 생산·폐기·로스 기록을 조회할 수 있습니다.
         */
        if ($user->role?->code === 'super_admin') {
            return $query;
        }

        /**
         * 본사 직원 및 본사 관리자
         *
         * 본사 계정은 전체 점포와 전체 부서의
         * 생산·폐기·로스 기록을 조회할 수 있습니다.
         *
         * 본사 여부는 store_id가 NULL인지 여부만으로
         * 판단하지 않고 department가 head_office인지
         * 함께 확인합니다.
         *
         * 또한 허용된 본사 Role인지 명시적으로 검사하여
         * 비정상적인 계정이 전체 데이터를 조회하지 못하도록 합니다.
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
         * 점포 정보가 없는 비정상 계정 차단
         *
         * 본사 계정이 아닌 사용자는
         * 반드시 소속 점포가 존재해야 합니다.
         *
         * store_id가 없는 경우 데이터 접근을 허용하지 않습니다.
         */
        if ($user->store_id === null) {
            return $query->whereRaw('1 = 0');
        }

        /**
         * 주방 헤드 셰프
         *
         * 자신의 점포에서 발생한
         * kitchen 부서 기록만 조회할 수 있습니다.
         *
         * 다른 점포 또는 hall 부서의 기록은
         * 조회할 수 없습니다.
         */
        if ($user->role?->code === 'kitchen_head') {
            return $query
                ->where('store_id', $user->store_id)
                ->where('department', 'kitchen');
        }

        /**
         * 홀 매니저
         *
         * 자신의 점포에서 발생한
         * hall 부서 기록만 조회할 수 있습니다.
         *
         * 다른 점포 또는 kitchen 부서의 기록은
         * 조회할 수 없습니다.
         */
        if ($user->role?->code === 'hall_manager') {
            return $query
                ->where('store_id', $user->store_id)
                ->where('department', 'hall');
        }

        /**
         * 일반 직원
         *
         * 자신의 점포와 자신의 부서에 해당하는
         * 생산·폐기·로스 기록만 조회할 수 있습니다.
         *
         * 예:
         *
         * 무역점 주방 직원
         * → 무역점 + kitchen
         *
         * 무역점 홀 직원
         * → 무역점 + hall
         *
         * 더현대서울점 주방 직원
         * → 더현대서울점 + kitchen
         */
        if ($user->role?->code === 'staff') {
            return $query
                ->where('store_id', $user->store_id)
                ->where('department', $user->department);
        }

        /**
         * 기본 접근 거부
         *
         * 위에서 명시적으로 허용하지 않은 Role은
         * 생산·폐기·로스 기록을 조회할 수 없습니다.
         *
         * 새로운 Role이 추가되었을 때 별도의 설정 없이
         * 생산 기록 접근 권한이 자동으로 열리지 않도록
         * 기본 거부 방식(Fail Closed)으로 처리합니다.
         */
        return $query->whereRaw('1 = 0');
    }
}