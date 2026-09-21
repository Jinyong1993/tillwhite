<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DayOffRequest extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['reviewed_at'=>'datetime','cancelled_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function dates(): HasMany { return $this->hasMany(DayOffRequestDate::class); }
}
