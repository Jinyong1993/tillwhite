<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    /**
     * 대량 할당 가능한 속성
     *
     * code       : 프로그램 내부에서 사용하는 직급 코드
     * name       : 화면에 표시할 직급명
     * sort_order : 직급 표시 순서
     * is_active  : 직급 사용 여부
     *
     * 직급(Position)은 시스템 권한(Role)과 별개의 개념입니다.
     *
     * Position
     * - 회사 조직상의 직급을 의미합니다.
     * - 예: 사원, 주임, 대리, 과장, 차장, 부장 등
     *
     * Role
     * - 시스템에서 사용할 수 있는 기능과 권한을 결정합니다.
     * - 예: staff, kitchen_head, hall_manager 등
     */
    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    /**
     * 직급 컬럼의 타입 변환 설정
     *
     * sort_order : 정렬 순서를 integer로 변환
     * is_active  : 직급 사용 여부를 boolean으로 변환
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 해당 직급을 현재 가지고 있는 직원 목록
     *
     * users.position_id를 기준으로
     * Position과 User를 일대다(One-to-Many) 관계로 연결합니다.
     *
     * 하나의 직급에는 여러 직원이 속할 수 있으며,
     * 각 직원은 현재 하나의 직급을 가집니다.
     *
     * 예:
     * $position->users
     *
     * 위와 같이 사용하면 해당 직급을 가진
     * 직원 목록을 조회할 수 있습니다.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}