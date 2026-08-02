<?php

namespace Modules\Travels\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelBookingItem extends Model
{
    protected $table = 'travel_booking_items';

    protected $fillable = [
        'travel_booking_id',
        'travel_category_id',
        'category_code',
        'category_name',
        'unit_price',
        'quantity',
        'total_price',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'total_price' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'travel_booking_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TravelCategory::class, 'travel_category_id');
    }
}
