<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class StoreDailyStatus extends Model
{
    /** 대량 할당 가능한 StoreDailyStatus 업무 속성입니다. */
    protected $fillable = ['store_id', 'work_date', 'status', 'reason', 'created_by', 'updated_by'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }
}
