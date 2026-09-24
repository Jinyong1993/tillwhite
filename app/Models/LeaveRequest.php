<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 휴가 신청 컬럼의 타입 변환 설정
     *
     * 휴가 시작일과 종료일은 date,
     * 휴가 사용량은 소수점 1자리,
     * 승인/반려 및 취소 시간은 datetime으로 변환합니다.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:1',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * 휴가를 신청한 직원
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 휴가 신청을 승인 또는 반려한 직원
     *
     * reviewed_by 컬럼을 users 테이블의
     * id와 연결합니다.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }
}