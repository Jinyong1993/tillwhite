<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 출퇴근 기록 컬럼의 타입 변환 설정
     *
     * 날짜/시간 컬럼은 datetime,
     * 상태값은 boolean으로 자동 변환합니다.
     */
    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_check_in_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'recognized_start_at' => 'datetime',
            'recognized_end_at' => 'datetime',
            'check_in_location_verified' => 'boolean',
            'check_out_location_verified' => 'boolean',
            'is_manually_modified' => 'boolean',
        ];
    }

    /**
     * 출퇴근 기록의 대상 직원
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 출퇴근 기록과 연결된 근무 스케줄
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * 출퇴근 기록이 발생한 점포
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}