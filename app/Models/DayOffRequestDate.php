<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DayOffRequestDate extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['request_date'=>'date']; }
    public function request(): BelongsTo { return $this->belongsTo(DayOffRequest::class,'day_off_request_id'); }
}
