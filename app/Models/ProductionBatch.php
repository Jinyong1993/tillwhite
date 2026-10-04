<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProductionBatch extends Model
{
    use SoftDeletes;

    /** 대량 할당 가능한 ProductionBatch 업무 속성입니다. */
    protected $fillable = ['store_id', 'product_id', 'batch_group_id', 'recipe_id', 'work_date', 'quantity', 'recipe_deviated', 'recipe_deviation_note', 'recipe_snapshot', 'note', 'lock_version', 'created_by', 'updated_by'];

    /** 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다. */
    protected function casts(): array
    {
        return ['work_date' => 'date', 'quantity' => 'integer', 'recipe_deviated' => 'boolean', 'recipe_snapshot' => 'array', 'lock_version' => 'integer'];
    }
}
