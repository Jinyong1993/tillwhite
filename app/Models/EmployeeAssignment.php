<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAssignment extends Model
{
    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 직원 소속 이력의 날짜 컬럼 타입 변환 설정
     *
     * effective_from : 해당 소속 정보의 적용 시작일
     * effective_to   : 해당 소속 정보의 적용 종료일
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}