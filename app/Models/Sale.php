<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Sale extends Model { protected $guarded=[]; protected function casts(): array { return ['sales_date'=>'date','confirmed_at'=>'datetime']; } public function store(): BelongsTo { return $this->belongsTo(Store::class); } public function items(): HasMany { return $this->hasMany(SaleItem::class); } public function refunds(): HasMany { return $this->hasMany(SaleRefund::class); } }
