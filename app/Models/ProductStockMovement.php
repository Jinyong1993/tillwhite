<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductStockMovement extends Model
{
    /** 대량 할당 가능한 ProductStockMovement 업무 속성입니다. */
    protected $fillable = ['stock_lot_id', 'store_id', 'product_id', 'work_date', 'movement_type', 'quantity', 'reason_code', 'note', 'created_by'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['work_date' => 'date', 'quantity' => 'integer'];
    }
}
