<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Services\Builders\RestUnitBookingQueryBuilder;

class RestUnitBooking extends CustomModel
{
    use SoftDeletes;

    protected $table = 'rest_unit_bookings';

    protected $fillable = [
        'rest_unit_id',
        'user_id',
        'start_date',
        'end_date',
        'status',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function newEloquentBuilder($query): RestUnitBookingQueryBuilder
    {
        return new RestUnitBookingQueryBuilder($query);
    }
    
    public function restUnit(): BelongsTo
    {
        return $this->belongsTo(RestUnit::class);
    }
}
