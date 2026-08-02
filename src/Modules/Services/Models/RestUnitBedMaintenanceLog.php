<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestUnitBedMaintenanceLog extends Model
{
    public const ACTION_TO_MAINTENANCE = 'to_maintenance';

    public const ACTION_RETURNED = 'returned_to_service';

    protected $table = 'rest_unit_bed_maintenance_logs';

    protected $fillable = [
        'rest_unit_bed_id',
        'action',
        'note',
    ];

    public function bed(): BelongsTo
    {
        return $this->belongsTo(RestUnitBed::class, 'rest_unit_bed_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_TO_MAINTENANCE => __('Sent to maintenance'),
            self::ACTION_RETURNED => __('Returned to service'),
            default => (string) $this->action,
        };
    }
}
