<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCode extends Model
{
    /**
     * 근무 코드 정보
     *
     * 점포와 부서별로 사용하는 근무 유형을 관리합니다.
     *
     * 각 근무 코드에는 출근 시간, 퇴근 시간,
     * 휴게시간 등의 기본 근무 정보를 저장합니다.
     *
     * 예:
     * A → 오픈 근무
     * B → 미들 근무
     * C → 마감 근무
     *
     * 실제 코드명과 근무 시간은
     * 점포 및 부서별 설정에 따라 다르게 구성할 수 있습니다.
     *
     * 같은 코드라도 점포나 부서가 다르면
     * 서로 다른 WorkCode로 관리할 수 있습니다.
     */

    /**
     * 대량 할당 가능한 속성
     *
     * store_id      : 근무 코드를 사용하는 점포
     * department    : 근무 코드를 사용하는 부서
     * code          : 프로그램 내부에서 사용하는 근무 코드
     * name          : 화면에 표시할 근무 코드명
     * start_time    : 기본 근무 시작 시간
     * end_time      : 기본 근무 종료 시간
     * break_minutes : 기본 휴게시간(분)
     * note          : 근무 코드 관련 메모
     * sort_order    : 근무 코드 표시 순서
     * is_active     : 근무 코드 사용 여부
     *
     * 근무 코드는 점포와 부서를 기준으로 관리하며,
     * 실제 근무 스케줄 생성 시 기본 근무 정보로 사용할 수 있습니다.
     */
    protected $fillable = [
        'store_id',
        'department',
        'code',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'note',
        'sort_order',
        'is_active',
    ];

    /**
     * 근무 코드 컬럼의 타입 변환 설정
     *
     * break_minutes : 휴게시간을 integer로 변환
     * sort_order    : 표시 순서를 integer로 변환
     * is_active     : 사용 여부를 boolean으로 변환
     *
     * start_time과 end_time은 현재 별도의 cast 없이
     * DB에 저장된 시간 값을 그대로 사용합니다.
     */
    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 근무 코드가 등록된 점포
     *
     * work_codes.store_id를 기준으로
     * WorkCode와 Store를 연결합니다.
     *
     * 하나의 점포에는 여러 근무 코드가 존재할 수 있으며,
     * 각각의 WorkCode는 하나의 점포에 속합니다.
     *
     * 부서 구분은 department 컬럼을 통해
     * 별도로 관리합니다.
     *
     * 예:
     * $workCode->store
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}