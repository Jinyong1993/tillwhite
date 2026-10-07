<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductionDailyClosure extends Model
{
    // 대량 할당 가능한 ProductionDailyClosure 업무 속성입니다.
    protected $fillable = ['store_id', 'work_date', 'status', 'daily_memo', 'closed_by', 'closed_at', 'updated_by'];

    // 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다.
    protected function casts(): array
    {
        return ['work_date' => 'date', 'closed_at' => 'datetime'];
    }
}
