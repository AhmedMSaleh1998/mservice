<?php

namespace Modules\Travels\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TravelCategory extends Model
{
    use HasTranslations;

    protected $table = 'travel_categories';

    protected $fillable = [
        'travel_id',
        'code',
        'name',
        'description',
        'price',
        'capacity',
        'sort_order',
        'is_active',
        'features',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'capacity' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public $translatable = [
        'name',
        'description',
    ];

    public function travel(): BelongsTo
    {
        return $this->belongsTo(Travel::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(TravelBookingItem::class);
    }
}
