<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveTransaction extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 휴가 발생/사용 내역 컬럼의 타입 변환 설정
     *
     * amount      : 휴가 증감 수량을 소수점 1자리로 변환
     * occurred_on : 휴가 발생 또는 사용일을 date로 변환
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:1',
            'occurred_on' => 'date',
        ];
    }

    /**
     * 휴가 발생/사용 내역의 대상 직원
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}