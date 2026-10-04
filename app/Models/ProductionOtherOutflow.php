<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProductionOtherOutflow extends Model
{
    use SoftDeletes;

    /** 대량 할당 가능한 ProductionOtherOutflow 업무 속성입니다. */
    protected $fillable = ['store_id', 'product_id', 'stock_lot_id', 'work_date', 'quantity', 'reason_code', 'reason_text', 'note', 'created_by'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['work_date' => 'date', 'quantity' => 'integer'];
    }
}
