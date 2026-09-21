<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Recipe extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function ingredients(): HasMany { return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order'); }
    public function steps(): HasMany { return $this->hasMany(RecipeStep::class)->orderBy('sort_order'); }
}
