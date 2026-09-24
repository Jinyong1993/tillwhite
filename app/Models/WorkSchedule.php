<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    /**
     * 직원 근무 스케줄 정보
     *
     * 직원이 특정 날짜에 어떤 근무를 하는지 저장합니다.
     *
     * 근무 대상 직원, 점포, 부서, 근무 코드와 함께
     * 실제 스케줄에 적용된 출퇴근 예정 시간 등의
     * 정보를 관리합니다.
     *
     * WorkCode가 근무 유형의 기본 설정이라면,
     * WorkSchedule은 특정 직원과 날짜에 실제로 배정된
     * 근무 계획이라고 볼 수 있습니다.
     *
     * 예:
     * 직원      → 홍길동
     * 근무일    → 2026-09-24
     * 근무 코드 → A
     * 점포      → 무역점
     * 부서      → 주방
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증, 스케줄 관리 권한 및
     * 점포·부서별 접근 범위는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 근무 스케줄 컬럼의 타입 변환 설정
     *
     * work_date            : 근무 예정일을 date로 변환
     * is_manually_modified : 수동 수정 여부를 boolean으로 변환
     *
     * is_manually_modified가 true이면
     * 자동 생성된 스케줄이 이후 관리자를 통해
     * 수동으로 변경되었는지 구분할 수 있습니다.
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_manually_modified' => 'boolean',
        ];
    }

    /**
     * 근무 스케줄이 배정된 직원
     *
     * work_schedules.user_id를 기준으로
     * WorkSchedule과 User를 연결합니다.
     *
     * 하나의 직원은 날짜별로 여러 근무 스케줄을
     * 가질 수 있으며, 각각의 WorkSchedule은
     * 하나의 직원에게 속합니다.
     *
     * 예:
     * $workSchedule->user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 근무가 이루어지는 점포
     *
     * work_schedules.store_id를 기준으로
     * WorkSchedule과 Store를 연결합니다.
     *
     * 직원의 현재 소속 점포와 별도로
     * 스케줄 당시의 점포 정보를 저장합니다.
     *
     * 본사 근무 스케줄의 경우
     * store_id가 NULL일 수 있습니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 스케줄에 적용된 근무 코드
     *
     * work_schedules.work_code_id를 기준으로
     * WorkSchedule과 WorkCode를 연결합니다.
     *
     * WorkCode에는 기본 출근 시간, 퇴근 시간,
     * 휴게시간 등의 근무 유형 정보가 저장됩니다.
     *
     * 예:
     * $workSchedule->workCode
     */
    public function workCode(): BelongsTo
    {
        return $this->belongsTo(WorkCode::class);
    }
}