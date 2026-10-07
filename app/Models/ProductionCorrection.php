<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ProductionCorrection extends Model
{
    // 마감 후 수정 사유와 변경 전후 묶음을 저장할 수 있는 속성입니다.
    protected $fillable = ['correction_group', 'store_id', 'work_date', 'reason', 'before_data', 'after_data', 'created_by'];

    // 연결 수정의 변경 전후 자료를 배열로 변환합니다.
    protected function casts(): array
    {
        return ['work_date' => 'date', 'before_data' => 'array', 'after_data' => 'array'];
    }
}
