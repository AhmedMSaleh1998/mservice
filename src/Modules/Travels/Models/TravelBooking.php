<?php

namespace Modules\Travels\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Travels\Builders\TravelBookingQueryBuilder;

class TravelBooking extends CustomModel
{
    use SoftDeletes;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID_SUCCESSFULLY = 'paid_successfully';

    public const STATUS_PAYMENT_EXPIRED = 'payment_expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'travel_bookings';

    protected $fillable = [
        'travel_id',
        'user_id',
        'status',
        'total_amount',
        'participants_count',
        'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'participants_count' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): TravelBookingQueryBuilder
    {
        return new TravelBookingQueryBuilder($query);
    }

    public function travel(): BelongsTo
    {
        return $this->belongsTo(Travel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Users\Models\User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TravelBookingItem::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }

    public static function blocksInventoryStatus(?string $status): bool
    {
        return ! in_array((string) $status, [
            self::STATUS_CANCELLED,
            self::STATUS_PAYMENT_EXPIRED,
        ], true);
    }
}
