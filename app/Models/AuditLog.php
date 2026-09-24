<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 감사 로그 데이터는 AuditService를 통해 생성하며,
     * 일반적인 사용자 입력으로 직접 생성하지 않습니다.
     */
    protected $guarded = [];

    /**
     * 감사 로그 컬럼의 타입 변환 설정
     *
     * 변경 전/후 데이터는 DB에 JSON 형태로 저장하고,
     * Laravel에서는 배열 형태로 사용할 수 있도록 변환합니다.
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * 감사 로그를 발생시킨 사용자
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}