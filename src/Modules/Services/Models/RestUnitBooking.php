<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Services\Builders\RestUnitBookingQueryBuilder;

class RestUnitBooking extends CustomModel
{
    use SoftDeletes;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID_SUCCESSFULLY = 'paid_successfully';

    public const STATUS_PAYMENT_EXPIRED = 'payment_expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'rest_unit_bookings';

    protected $fillable = [
        'rest_unit_id',
        'user_id',
        'start_date',
        'end_date',
        'status',
        'unit_type',
        'total_price',
        'is_active',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_price' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): RestUnitBookingQueryBuilder
    {
        return new RestUnitBookingQueryBuilder($query);
    }

    public function restUnit(): BelongsTo
    {
        return $this->belongsTo(RestUnit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Users\Models\User::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }

    public static function normalizeUnitType(?string $value): ?string
    {
        return RestUnit::normalizeUnitType($value);
    }

    public static function blocksInventoryStatus(?string $status): bool
    {
        return ! in_array((string) $status, [
            self::STATUS_CANCELLED,
            self::STATUS_PAYMENT_EXPIRED,
        ], true);
    }
}
