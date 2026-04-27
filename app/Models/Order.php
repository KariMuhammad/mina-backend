<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    // Canonical status constants — these are the ONLY values stored in DB
    const STATUS_PENDING          = 'Pending';
    const STATUS_PROCESSING       = 'Processing';
    const STATUS_PREPARING        = 'Preparing';
    const STATUS_OUT_FOR_DELIVERY = 'Out_for_Delivery';
    const STATUS_COMPLETED        = 'Completed';
    const STATUS_CANCELLED        = 'Cancelled';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING          => 'معلق',
            self::STATUS_PROCESSING       => 'قيد المعالجة',
            self::STATUS_PREPARING        => 'جاري تجهيز الطلب',
            self::STATUS_OUT_FOR_DELIVERY => 'الطلب في الطريق',
            self::STATUS_COMPLETED        => 'تم التوصيل',
            self::STATUS_CANCELLED        => 'ملغي',
        ];
    }

    protected $fillable = [
        'user_id',
        'guest_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'address_id',
        'delivery_zone_id',
        'subtotal',
        'discount_amount',
        'final_price',
        'coupon_id',
        'coupon_code',
        'total_price',
        'delivery_price',
        'products_total',
        'customer_name',
        'customer_phone',
        'customer_address',
        'status',
        'payment_status',
        'payment_method',
        'notes',
        'tracking_number',
        'shipping_address',
        'whatsapp_sent',
        'whatsapp_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'whatsapp_sent'    => 'boolean',
            'whatsapp_sent_at' => 'datetime',
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

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
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
