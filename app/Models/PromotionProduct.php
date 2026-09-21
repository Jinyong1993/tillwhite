<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PromotionProduct extends Model { protected $guarded=[]; protected function casts(): array { return ['discount_value'=>'decimal:2']; } }
