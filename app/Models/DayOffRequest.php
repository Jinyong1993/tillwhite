<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayOffRequest extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 희망휴무 신청 컬럼의 타입 변환 설정
     *
     * 승인/반려 처리 시간과 취소 시간을
     * datetime 객체로 자동 변환합니다.
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * 희망휴무를 신청한 직원
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 희망휴무 신청에 포함된 날짜 목록
     *
     * 하나의 희망휴무 신청에
     * 여러 개의 희망 날짜를 등록할 수 있습니다.
     */
    public function dates(): HasMany
    {
        return $this->hasMany(DayOffRequestDate::class);
    }
}