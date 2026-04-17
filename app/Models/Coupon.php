<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'discount_amount', 'discount_percent', 'minimum_order',
        'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
