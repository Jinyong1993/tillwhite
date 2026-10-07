<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProductionLoss extends Model
{
    use SoftDeletes;

    // 대량 할당 가능한 ProductionLoss 업무 속성입니다.
    protected $fillable = [
        'store_id',
        'product_id',
        'production_batch_id',
        'stock_lot_id',
        'work_date',
        'attribution_date',
        'quantity',
        'note',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    // 날짜, 수량, 스냅샷 값을 업무에 맞는 타입으로 변환합니다.
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'attribution_date' => 'date',
            'quantity' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    // 사유별 배분 내역을 상세·분석 화면에서 재사용합니다.
    public function reasons()
    {
        return $this->hasMany(ProductionLossReason::class, 'production_loss_id');
    }
}
