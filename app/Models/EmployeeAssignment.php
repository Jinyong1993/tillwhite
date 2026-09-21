<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmployeeAssignment extends Model { protected $guarded=[]; protected function casts(): array { return ['effective_from'=>'date','effective_to'=>'date']; } }
