<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestUnitBed extends Model
{
    use SoftDeletes;

    public const STATUS_IN_SERVICE = 'in_service';

    public const STATUS_MAINTENANCE = 'maintenance';

    protected $table = 'rest_unit_beds';

    protected $fillable = [
        'rest_unit_id',
        'label',
        'status',
        'maintenance_note',
        'maintenance_started_at',
    ];

    protected $casts = [
        'maintenance_started_at' => 'datetime',
    ];

    public function restUnit(): BelongsTo
    {
        return $this->belongsTo(RestUnit::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RestUnitBooking::class, 'rest_unit_bed_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(RestUnitBedMaintenanceLog::class, 'rest_unit_bed_id');
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

        $this->maintenanceLogs()->create([
            'action' => RestUnitBedMaintenanceLog::ACTION_TO_MAINTENANCE,
            'note' => $note,
        ]);
    }

    public function returnToService(): void
    {
        $note = $this->maintenance_note;

        $this->forceFill([
            'status' => self::STATUS_IN_SERVICE,
            'maintenance_note' => null,
            'maintenance_started_at' => null,
        ])->save();

        $this->maintenanceLogs()->create([
            'action' => RestUnitBedMaintenanceLog::ACTION_RETURNED,
            'note' => $note,
        ]);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_IN_SERVICE => __('In service'),
            self::STATUS_MAINTENANCE => __('Under maintenance'),
        ];
    }
}
