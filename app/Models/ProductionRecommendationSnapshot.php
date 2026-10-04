<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductionRecommendationSnapshot extends Model
{
    /** 대량 할당 가능한 ProductionRecommendationSnapshot 업무 속성입니다. */
    protected $fillable = ['store_id', 'product_id', 'production_batch_id', 'target_date', 'center_quantity', 'range_min', 'range_max', 'confidence', 'calculation_version', 'reasons', 'excluded_conditions', 'data_from', 'data_to', 'referenced', 'deviation_reason', 'generated_at'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['target_date' => 'date', 'center_quantity' => 'integer', 'range_min' => 'integer', 'range_max' => 'integer', 'reasons' => 'array', 'excluded_conditions' => 'array', 'data_from' => 'date', 'data_to' => 'date', 'referenced' => 'boolean', 'generated_at' => 'datetime'];
    }
}
