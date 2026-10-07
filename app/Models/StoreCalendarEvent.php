<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class StoreCalendarEvent extends Model
{
    use SoftDeletes;

    // 캘린더 행사에서 입력 가능한 업무 속성입니다.
    protected $fillable = ['store_id', 'event_type', 'title', 'start_date', 'end_date', 'discount_type', 'discount_value', 'order_quantity', 'memo', 'created_by', 'updated_by'];

    // 행사 기간과 할인 값을 업무에 맞는 타입으로 변환합니다.
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'discount_value' => 'decimal:2', 'order_quantity' => 'integer'];
    }

    // 특정 제품 행사일 때 적용되는 제품 목록이며 비어 있으면 점포 전체 행사입니다.
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'store_calendar_event_products', 'event_id', 'product_id')->withTimestamps();
    }
}
