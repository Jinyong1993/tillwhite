<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductPrice extends Model { protected $guarded=[]; protected function casts(): array { return ['price'=>'integer','effective_from'=>'date','effective_to'=>'date']; } public function product(): BelongsTo { return $this->belongsTo(Product::class); } }
