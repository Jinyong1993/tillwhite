<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class StoreDailyWeather extends Model
{
    // 대량 할당 가능한 StoreDailyWeather 업무 속성입니다.
    protected $fillable = ['store_id', 'work_date', 'average_temperature', 'minimum_temperature', 'maximum_temperature', 'average_humidity', 'precipitation', 'condition', 'collection_status', 'initial_snapshot', 'corrected_at'];

    // 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다.
    protected function casts(): array
    {
        return ['work_date' => 'date', 'initial_snapshot' => 'array', 'corrected_at' => 'datetime'];
    }
}
