<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayOffRequestDate extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증은
     * Controller에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 희망휴무 날짜 컬럼의 타입 변환 설정
     *
     * request_date를 Laravel의
     * 날짜 객체로 자동 변환합니다.
     */
    protected function casts(): array
    {
        return [
            'request_date' => 'date',
        ];
    }

    /**
     * 해당 날짜가 속한 희망휴무 신청
     *
     * 여러 희망휴무 날짜가
     * 하나의 DayOffRequest에 속합니다.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(
            DayOffRequest::class,
            'day_off_request_id'
        );
    }
}