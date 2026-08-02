<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Core\Models\Province;
use Modules\Services\Builders\RestUnitQueryBuilder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class RestUnit extends CustomModel implements HasMedia
{
    use HasTranslations, SoftDeletes, InteractsWithMedia;

    /** Kind of the rest unit (value stored in the `type` column). */
    public const TYPE_BEDS = 'beds';

    public const TYPE_ROOMS = 'rooms';

    public const TYPE_WHOLE_UNIT = 'whole_unit';

    public const STATUS_IN_SERVICE = 'in_service';

    public const STATUS_MAINTENANCE = 'maintenance';

    protected $table = 'rest_units';

    protected $fillable = [
        'name',
        'address',
        'province_id',
        'type',
        'price',
        'status',
        'maintenance_note',
        'is_active',
    ];

    public $translatable = ['name', 'address'];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'type' => self::TYPE_BEDS,
        'status' => self::STATUS_IN_SERVICE,
    ];

    public function newEloquentBuilder($query): RestUnitQueryBuilder
    {
        return new RestUnitQueryBuilder($query);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(RestUnitRoom::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(RestUnitBed::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RestUnitBooking::class);
    }

    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'orderable');
    }

    public function scopeActive()
    {
        return $this->where('is_active', true);
    }

    public function isBeds(): bool
    {
        return $this->type === self::TYPE_BEDS;
    }

    public function isRooms(): bool
    {
        return $this->type === self::TYPE_ROOMS;
    }

    public function isWholeUnit(): bool
    {
        return $this->type === self::TYPE_WHOLE_UNIT;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    /** Total in-service bookable places, regardless of any period. */
    public function totalPlaces(): int
    {
        return match ($this->type) {
            self::TYPE_BEDS => $this->beds->where('status', RestUnitBed::STATUS_IN_SERVICE)->count(),
            self::TYPE_ROOMS => $this->rooms->where('status', RestUnitRoom::STATUS_IN_SERVICE)->count(),
            self::TYPE_WHOLE_UNIT => $this->isUnderMaintenance() ? 0 : 1,
            default => 0,
        };
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_BEDS => __('Beds'),
            self::TYPE_ROOMS => __('Rooms'),
            self::TYPE_WHOLE_UNIT => __('Whole unit'),
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::typeOptions()[(string) $type] ?? (string) $type;
    }
}
