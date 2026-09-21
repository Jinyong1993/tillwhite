<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WorkSchedule extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['work_date'=>'date','is_manually_modified'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function workCode(): BelongsTo { return $this->belongsTo(WorkCode::class); }
}
