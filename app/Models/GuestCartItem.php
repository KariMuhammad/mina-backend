<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestCartItem extends Model
{
    protected $fillable = ['guest_id', 'product_id', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

