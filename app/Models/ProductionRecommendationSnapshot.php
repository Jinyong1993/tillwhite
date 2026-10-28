<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRecommendationSnapshot extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'production_batch_id',
        'target_date',
        'center_quantity',
        'range_min',
        'range_max',
        'confidence',
        'calculation_version',
        'reasons',
        'excluded_conditions',
        'adjustment_meta',
        'data_from',
        'data_to',
        'referenced',
        'deviation_reason',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',

            'center_quantity' => 'integer',
            'range_min' => 'integer',
            'range_max' => 'integer',

            'reasons' => 'array',
            'excluded_conditions' => 'array',

            'adjustment_meta' => 'array',

            'data_from' => 'date',
            'data_to' => 'date',

            'referenced' => 'boolean',

            'generated_at' => 'datetime',
        ];
    }
}