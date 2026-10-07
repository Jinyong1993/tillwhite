<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductionLossReason extends Model
{
    // 로스 사유별 배분 수량입니다.
    protected $fillable = ['production_loss_id', 'reason_code', 'reason_text', 'quantity'];
}
