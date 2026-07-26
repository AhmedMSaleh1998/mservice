<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestUnitRoom extends Model
{
    use SoftDeletes;

    public const STATUS_IN_SERVICE = 'in_service';

    public const STATUS_MAINTENANCE = 'maintenance';

    protected $table = 'rest_unit_rooms';

    protected $fillable = [
        'rest_unit_id',
        'room_type_id',
        'name',
        'price',
        'status',
        'maintenance_note',
        'maintenance_started_at',
    ];

    protected $casts = [
        'price' => 'float',
        'maintenance_started_at' => 'datetime',
    ];

    public function restUnit(): BelongsTo
    {
        return $this->belongsTo(RestUnit::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RestUnitBooking::class, 'rest_unit_room_id');
    }

    public function scopeInService(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_SERVICE);
    }

    public function isInService(): bool
    {
        return $this->status === self::STATUS_IN_SERVICE;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    public function sendToMaintenance(?string $note = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_MAINTENANCE,
            'maintenance_note' => $note,
            'maintenance_started_at' => now(),
        ])->save();
    }

    public function returnToService(): void
    {
        $this->forceFill([
            'status' => self::STATUS_IN_SERVICE,
            'maintenance_note' => null,
            'maintenance_started_at' => null,
        ])->save();
    }

    public function typeName(): ?string
    {
        return $this->roomType?->getTranslation('name', app()->getLocale());
    }

    public function label(): string
    {
        $type = $this->typeName();

        return trim(($this->name ?: __('Room')).($type ? ' — '.$type : ''));
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_IN_SERVICE => __('In service'),
            self::STATUS_MAINTENANCE => __('Under maintenance'),
        ];
    }
}
