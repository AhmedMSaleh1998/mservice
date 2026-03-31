<?php

namespace Modules\Memberships\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;

class MembershipRequest extends CustomModel
{
    use HasFactory;

    public const DELIVERY_STATUS_PENDING = 'pending';
    public const DELIVERY_STATUS_PREPARING = 'preparing';
    public const DELIVERY_STATUS_SHIPPED = 'shipped';
    public const DELIVERY_STATUS_DELIVERED = 'delivered';
    public const DELIVERY_STATUS_FAILED = 'failed';
    public const DELIVERY_STATUS_RETURNED = 'returned';

    protected $fillable = [
        'user_id',
        'full_name',
        'specialty',
        'degree',
        'registration_number',
        'delivery_method',
        'address',
        'status',
        'delivery_status',
        'printing_cost',
        'delivery_cost',
        'subscription_cost',
        'total_amount',
        'user_address_id',
        'payment_method'
    ];

    protected $casts = [
        'address' => 'array',
        'printing_cost' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'subscription_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public static function deliveryStatusOptions(): array
    {
        return [
            self::DELIVERY_STATUS_PENDING => __('Pending'),
            self::DELIVERY_STATUS_PREPARING => __('Preparing'),
            self::DELIVERY_STATUS_SHIPPED => __('Shipped'),
            self::DELIVERY_STATUS_DELIVERED => __('Delivered'),
            self::DELIVERY_STATUS_FAILED => __('Failed'),
            self::DELIVERY_STATUS_RETURNED => __('Returned'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function newEloquentBuilder($query): \Modules\Memberships\Builders\MembershipRequestQueryBuilder
    {
        return new \Modules\Memberships\Builders\MembershipRequestQueryBuilder($query);
    }

    public function userAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }
}
