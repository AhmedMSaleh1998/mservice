<?php

namespace Modules\Certificates\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Certificates\Builders\CertificateRequestBuilder;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CertificateRequest extends CustomModel
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID_SUCCESSFULLY = 'paid_successfully';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_STATUS_PENDING = 'pending';
    public const DELIVERY_STATUS_PREPARING = 'preparing';
    public const DELIVERY_STATUS_SHIPPED = 'shipped';
    public const DELIVERY_STATUS_DELIVERED = 'delivered';
    public const DELIVERY_STATUS_FAILED = 'failed';
    public const DELIVERY_STATUS_RETURNED = 'returned';

    protected $fillable = [
        'user_id', 'certificate_id', 'delivery_method', 'user_address_id',
        'phone', 'email', 'status', 'delivery_status', 'printing_cost', 'delivery_cost', 'subscription_cost',
        'total_amount'
    ];

    protected $casts = [
        'address' => 'array',
        'printing_cost' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'subscription_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function newEloquentBuilder($query): CertificateRequestBuilder
    {
        return new CertificateRequestBuilder($query);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING_PAYMENT => __('Pending Payment'),
            self::STATUS_PAID_SUCCESSFULLY => __('Paid Successfully'),
            self::STATUS_PROCESSING => __('Processing'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

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

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
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
