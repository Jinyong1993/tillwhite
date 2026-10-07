<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductionWasteReason extends Model
{
    // 폐기 사유별 배분 수량입니다.
    protected $fillable = ['production_waste_id', 'reason_code', 'reason_text', 'quantity'];
}
