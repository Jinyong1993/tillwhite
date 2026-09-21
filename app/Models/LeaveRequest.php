<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LeaveRequest extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['start_date'=>'date','end_date'=>'date','amount'=>'decimal:1','reviewed_at'=>'datetime','cancelled_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
