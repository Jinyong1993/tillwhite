<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductionConfirmation extends Model
{
    /** 대량 할당 가능한 ProductionConfirmation 업무 속성입니다. */
    protected $fillable = ['store_id', 'product_id', 'work_date', 'production_confirmed', 'zero_production_reason', 'zero_production_note', 'loss_confirmed', 'waste_confirmed', 'disposition_confirmed', 'confirmed_by'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['work_date' => 'date', 'production_confirmed' => 'boolean', 'loss_confirmed' => 'boolean', 'waste_confirmed' => 'boolean', 'disposition_confirmed' => 'boolean'];
    }
}
