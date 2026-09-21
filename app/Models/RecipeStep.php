<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecipeStep extends Model { protected $guarded=[]; protected function casts(): array { return ['sort_order'=>'integer']; } }
