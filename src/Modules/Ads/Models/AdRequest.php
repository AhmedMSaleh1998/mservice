<?php

namespace Modules\Ads\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Ads\Builders\AdRequestQueryBuilder;
use Modules\Ads\Enums\AdDisplayStateEnum;
use Modules\Ads\Enums\AdRequestStatusEnum;
use Modules\Core\Models\Order;
use Modules\Core\Models\CustomModel;
use Modules\Users\Models\User;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AdRequest extends CustomModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const ACTIVE_RESERVATION_STATUSES = [
        'pending_payment',
        'checkout_pending',
    ];

    public const EDITABLE_PRE_PAYMENT_STATUSES = [
        'pending_payment',
        'checkout_pending',
    ];

    protected $fillable = [
        'user_id',
        'ad_space_id',
        'duration_months',
        'price_per_month',
        'total_amount',
        'ad_text',
        'design_image',
        'status',
        'is_visible',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price_per_month' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_visible' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): AdRequestQueryBuilder
    {
        return new AdRequestQueryBuilder($query);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adSpace(): BelongsTo
    {
        return $this->belongsTo(AdSpace::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }

    public function statusEnum(): ?AdRequestStatusEnum
    {
        return AdRequestStatusEnum::tryFrom((string) $this->status);
    }

    /** Approved at some point, whether still running or already ended. */
    public function isPublished(): bool
    {
        return in_array($this->statusEnum(), AdRequestStatusEnum::publishedStatuses(), true);
    }

    /** Approved and still inside its display period (it may still be hidden). */
    public function isRunning(): bool
    {
        return $this->statusEnum() === AdRequestStatusEnum::APPROVED
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function displayState(): AdDisplayStateEnum
    {
        return $this->isRunning() ? AdDisplayStateEnum::RUNNING : AdDisplayStateEnum::ENDED;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('design_image')->singleFile();
    }
}
