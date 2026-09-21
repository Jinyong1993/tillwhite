<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WorkCode extends Model {
    protected $fillable=['store_id','department','code','name','start_time','end_time','break_minutes','note','sort_order','is_active'];
    protected function casts(): array { return ['break_minutes'=>'integer','sort_order'=>'integer','is_active'=>'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}
