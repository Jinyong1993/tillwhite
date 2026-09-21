<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LeaveTransaction extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['amount'=>'decimal:1','occurred_on'=>'date']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
