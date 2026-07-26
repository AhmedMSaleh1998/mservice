<?php

namespace Modules\Services\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Services\Builders\RestUnitBookingQueryBuilder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RestUnitBooking extends CustomModel implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID_SUCCESSFULLY = 'paid_successfully';

    public const STATUS_PAYMENT_EXPIRED = 'payment_expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const BENEFICIARY_MEMBER = 'member';

    public const BENEFICIARY_MARTYR_FAMILY = 'martyr_family';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_FAWRY = 'fawry';

    public const RECEIPT_COLLECTION = 'payment_receipt';

    protected $table = 'rest_unit_bookings';

    protected $fillable = [
        'rest_unit_id',
        'rest_unit_room_id',
        'rest_unit_bed_id',
        'user_id',
        'beneficiary_type',
        'beneficiary_name',
        'beneficiary_card_number',
        'beneficiary_reg_number',
        'payment_reference',
        'start_date',
        'end_date',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'unit_type',
        'total_price',
        'payment_method',
        'is_active',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_price' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $attributes = [
        'beneficiary_type' => self::BENEFICIARY_MEMBER,
    ];

    public function newEloquentBuilder($query): RestUnitBookingQueryBuilder
    {
        return new RestUnitBookingQueryBuilder($query);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::RECEIPT_COLLECTION)->singleFile();
    }

    public function restUnit(): BelongsTo
    {
        return $this->belongsTo(RestUnit::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(RestUnitRoom::class, 'rest_unit_room_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(RestUnitBed::class, 'rest_unit_bed_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Users\Models\User::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }

    public function isForMartyrFamily(): bool
    {
        return $this->beneficiary_type === self::BENEFICIARY_MARTYR_FAMILY;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** Payment methods that are settled offline and never need an online refund. */
    private const OFFLINE_PAYMENT_METHODS = ['', 'free', 'cash', 'offline', 'manual_offline'];

    /** True when a paid online payment (Fawry / gateway) must be refunded on cancellation. */
    public function requiresOnlineRefund(): bool
    {
        $order = $this->relationLoaded('order') ? $this->getRelation('order') : $this->order()->first();

        if (! $order) {
            return false;
        }

        $paid = $order->status === self::STATUS_PAID_SUCCESSFULLY
            || strtoupper((string) $order->gateway_status) === 'PAID'
            || filled($order->paid_at);

        $method = strtolower(trim((string) $order->payment_method));

        return $paid && ! in_array($method, self::OFFLINE_PAYMENT_METHODS, true);
    }

    public function paymentMethodLabel(): ?string
    {
        $order = $this->relationLoaded('order') ? $this->getRelation('order') : $this->order()->first();

        return $order?->payment_method;
    }

    public function cancel(?string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ])->save();
    }

    public function guestName(): ?string
    {
        if ($this->isForMartyrFamily()) {
            return $this->beneficiary_name
                ?: ($this->beneficiary_card_number ? __('Martyr family (:id)', ['id' => $this->beneficiary_card_number]) : __('Martyr family'));
        }

        return $this->user?->name;
    }

    public function targetLabel(): string
    {
        if ($this->rest_unit_room_id) {
            return (string) ($this->room?->label() ?? $this->unit_type ?? __('Room'));
        }

        if ($this->rest_unit_bed_id) {
            return (string) ($this->bed?->label ?? __('Bed'));
        }

        return $this->restUnit?->isWholeUnit() ? __('Whole unit') : (string) ($this->unit_type ?? __('Room'));
    }

    public static function blocksInventoryStatus(?string $status): bool
    {
        return ! in_array((string) $status, [
            self::STATUS_CANCELLED,
            self::STATUS_PAYMENT_EXPIRED,
        ], true);
    }

    public static function beneficiaryTypeOptions(): array
    {
        return [
            self::BENEFICIARY_MEMBER => __('Registered member'),
            self::BENEFICIARY_MARTYR_FAMILY => __('Martyr family'),
        ];
    }

    /** Payment methods offered when booking from the dashboard (offline only). */
    public static function paymentMethodOptions(): array
    {
        return [
            self::PAYMENT_CASH => __('Cash'),
            self::PAYMENT_BANK_TRANSFER => __('Bank transfer'),
        ];
    }

    public static function offlinePaymentMethods(): array
    {
        return [self::PAYMENT_CASH, self::PAYMENT_BANK_TRANSFER];
    }

    public function isOnlinePayment(): bool
    {
        return $this->payment_method === self::PAYMENT_FAWRY;
    }

    /** The stored Fawry checkout URL (persists on the order so it is never lost). */
    public function fawryLink(): ?string
    {
        if ($this->payment_method !== self::PAYMENT_FAWRY) {
            return null;
        }

        $order = $this->relationLoaded('order') ? $this->getRelation('order') : $this->order()->first();

        return $order?->checkout_url;
    }

    /**
     * Peak number of concurrently-active bookings within the date range.
     * Pass a pre-filtered collection (e.g. one room group's bookings, or all bed bookings).
     */
    public static function peakConcurrent(Collection $bookings, string $fromDate, string $toDate): int
    {
        if ($bookings->isEmpty()) {
            return 0;
        }

        $rangeStart = Carbon::parse($fromDate)->startOfDay();
        $rangeEnd = Carbon::parse($toDate)->startOfDay();
        $peak = 0;

        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
            $active = $bookings->filter(
                fn (RestUnitBooking $booking): bool => static::bookingTouchesDate($booking, $cursor)
            )->count();
            $peak = max($peak, $active);
        }

        return $peak;
    }

    private static function bookingTouchesDate(RestUnitBooking $booking, Carbon $date): bool
    {
        $startDate = $booking->start_date instanceof Carbon
            ? $booking->start_date->copy()->startOfDay()
            : Carbon::parse((string) $booking->start_date)->startOfDay();
        $endDate = $booking->end_date instanceof Carbon
            ? $booking->end_date->copy()->startOfDay()
            : Carbon::parse((string) $booking->end_date)->startOfDay();

        return $startDate->lte($date) && $endDate->gte($date);
    }
}
