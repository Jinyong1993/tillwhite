<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Promotion extends Model { protected $guarded=[]; protected function casts(): array { return ['start_date'=>'date','end_date'=>'date','is_active'=>'boolean']; } public function store(): BelongsTo { return $this->belongsTo(Store::class); } public function products(): HasMany { return $this->hasMany(PromotionProduct::class); } }
