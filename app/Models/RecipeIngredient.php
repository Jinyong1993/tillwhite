<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecipeIngredient extends Model { protected $guarded=[]; protected function casts(): array { return ['quantity'=>'decimal:3','sort_order'=>'integer']; } }
