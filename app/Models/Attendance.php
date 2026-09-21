<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Attendance extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['scheduled_start_at'=>'datetime','scheduled_end_at'=>'datetime','actual_check_in_at'=>'datetime','actual_check_out_at'=>'datetime','recognized_start_at'=>'datetime','recognized_end_at'=>'datetime','check_in_location_verified'=>'boolean','check_out_location_verified'=>'boolean','is_manually_modified'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function workSchedule(): BelongsTo { return $this->belongsTo(WorkSchedule::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}
