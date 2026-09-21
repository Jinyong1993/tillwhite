<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SaleRefund extends Model { protected $guarded=[]; protected function casts(): array { return ['refund_date'=>'date']; } }
