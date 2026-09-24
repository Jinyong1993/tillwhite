<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    /**
     * 점포 정보
     *
     * Till White의 각 점포 정보를 관리합니다.
     *
     * 점포는 운영 중단이나 폐점 이후에도
     * 생산, 근무, 매출 등의 과거 기록과 연결될 수 있으므로
     * Soft Delete를 사용하여 데이터를 보존합니다.
     *
     * 점포의 운영 상태는 삭제 여부와 별도로
     * status 컬럼을 통해 관리합니다.
     *
     * 상태:
     * active   : 정상 운영 중
     * inactive : 일시적으로 비활성화된 점포
     * closed   : 폐점한 점포
     *
     * 본사는 점포가 아니므로 Store 데이터로 생성하지 않고,
     * 본사 직원은 users.store_id를 NULL로 관리합니다.
     */

    /**
     * 대량 할당 가능한 속성
     *
     * store_code : 프로그램 내부에서 사용하는 점포 코드
     * name       : 화면에 표시할 점포명
     * status     : 점포 운영 상태
     * opened_at  : 점포 영업 시작일
     * closed_at  : 점포 폐점일
     *
     * store_code는 DB의 기본키인 id와 별도로 사용하는
     * 업무 및 시스템상의 점포 식별 코드입니다.
     *
     * 예:
     * 1 → 무역점
     * 2 → 더현대서울점
     */
    protected $fillable = [
        'store_code',
        'name',
        'status',
        'opened_at',
        'closed_at',
    ];

    /**
     * 점포 컬럼의 타입 변환 설정
     *
     * opened_at : 점포 영업 시작일을 date로 변환
     * closed_at : 점포 폐점일을 date로 변환
     *
     * status는 active, inactive, closed 등의
     * 문자열 상태 코드를 그대로 사용하므로
     * 별도의 타입 변환을 하지 않습니다.
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    /**
     * 점포에 소속된 사용자 목록
     *
     * users.store_id를 기준으로
     * Store와 User를 일대다(One-to-Many) 관계로 연결합니다.
     *
     * 하나의 점포에는 여러 직원이 소속될 수 있으며,
     * 각 점포 직원은 하나의 점포에 소속됩니다.
     *
     * 본사 직원은 users.store_id가 NULL이므로
     * 이 관계에 포함되지 않습니다.
     *
     * 예:
     * $store->users
     *
     * 위와 같이 사용하면 해당 점포에 연결된
     * 사용자 목록을 조회할 수 있습니다.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}