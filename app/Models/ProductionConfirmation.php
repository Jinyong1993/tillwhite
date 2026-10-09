<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionConfirmation extends Model
{
    /**
     * 제품별·날짜별 생산, 로스, 폐기, 이월 확인 상태를 저장합니다.
     * 각 업무의 확인 상태는 독립적으로 관리합니다.
     */
    protected $fillable = [
        'store_id',
        'product_id',
        'work_date',
        'production_confirmed',
        'zero_production_reason',
        'zero_production_reason_text',
        'zero_production_note',
        'loss_confirmed',
        'waste_confirmed',
        'disposition_confirmed',
        'confirmed_by',
    ];

    /**
     * 업무 날짜와 확인 상태를 올바른 자료형으로 변환합니다.
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'production_confirmed' => 'boolean',
            'loss_confirmed' => 'boolean',
            'waste_confirmed' => 'boolean',
            'disposition_confirmed' => 'boolean',
        ];
    }
}