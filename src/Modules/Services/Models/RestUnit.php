<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const TYPE_SINGLE_ROOM = 'single_room';

    public const TYPE_DOUBLE_ROOM = 'double_room';

    public const TYPE_TRIPLE_ROOM = 'triple_room';

    private const TYPE_ALIASES = [
        self::TYPE_SINGLE_ROOM => self::TYPE_SINGLE_ROOM,
        'single_rooms' => self::TYPE_SINGLE_ROOM,
        self::TYPE_DOUBLE_ROOM => self::TYPE_DOUBLE_ROOM,
        'double_rooms' => self::TYPE_DOUBLE_ROOM,
        self::TYPE_TRIPLE_ROOM => self::TYPE_TRIPLE_ROOM,
        'triple_rooms' => self::TYPE_TRIPLE_ROOM,
        'single_bed' => self::TYPE_TRIPLE_ROOM,
    ];

    private const INVENTORY_COLUMNS = [
        self::TYPE_SINGLE_ROOM => 'single_rooms',
        self::TYPE_DOUBLE_ROOM => 'double_rooms',
        self::TYPE_TRIPLE_ROOM => 'triple_rooms',
    ];

    private const PRICE_COLUMNS = [
        self::TYPE_SINGLE_ROOM => 'single_room_price',
        self::TYPE_DOUBLE_ROOM => 'double_room_price',
        self::TYPE_TRIPLE_ROOM => 'triple_room_price',
    ];

    protected $table = 'rest_units';

    protected $fillable = [
        'name',
        'address',
        'province_id',
        'single_rooms',
        'double_rooms',
        'triple_rooms',
        'is_active',
        'single_room_price',
        'double_room_price',
        'triple_room_price',
    ];

    public $translatable = ['name', 'address'];

    protected $casts = [
        'single_room_price' => 'float',
        'double_room_price' => 'float',
        'triple_room_price' => 'float',
        'is_active' => 'boolean',
    ];

    public function newEloquentBuilder($query): RestUnitQueryBuilder
    {
        return new RestUnitQueryBuilder($query);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function bookings()
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

    public static function supportedUnitTypes(): array
    {
        return [
            self::TYPE_SINGLE_ROOM,
            self::TYPE_DOUBLE_ROOM,
            self::TYPE_TRIPLE_ROOM,
        ];
    }

    public static function normalizeUnitType(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return self::TYPE_ALIASES[$normalized] ?? null;
    }

    public static function inventoryColumnForType(string $type): ?string
    {
        $normalizedType = self::normalizeUnitType($type);

        return $normalizedType ? (self::INVENTORY_COLUMNS[$normalizedType] ?? null) : null;
    }

    public static function priceColumnForType(string $type): ?string
    {
        $normalizedType = self::normalizeUnitType($type);

        return $normalizedType ? (self::PRICE_COLUMNS[$normalizedType] ?? null) : null;
    }
}
