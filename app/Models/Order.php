<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'guest_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'address_id',
        'subtotal',
        'discount_amount',
        'coupon_id',
        'coupon_code',
        'total_price',
        'status',
        'payment_status',
        'payment_method',
        'notes',
        'tracking_number',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderHistories()
    {
        return $this->hasMany(OrderHistory::class)->latest('created_at');
    }
}
