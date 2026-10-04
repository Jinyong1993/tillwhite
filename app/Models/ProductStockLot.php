<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductStockLot extends Model
{
    /** 대량 할당 가능한 ProductStockLot 업무 속성입니다. */
    protected $fillable = ['store_id', 'product_id', 'production_batch_id', 'origin_production_date', 'initial_quantity', 'remaining_quantity', 'status'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['origin_production_date' => 'date', 'initial_quantity' => 'integer', 'remaining_quantity' => 'integer'];
    }
}
