<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Province;
use Modules\Services\Builders\RestUnitQueryBuilder;
use Spatie\Translatable\HasTranslations;

class RestUnit extends CustomModel
{
    use HasTranslations, SoftDeletes;

    protected $table = 'rest_units';
    protected $fillable = [
        'name', 'address', 'province_id', 'single_rooms', 'double_rooms', 'single_bed', 'is_active',
        'single_room_price', 'double_room_price', 'single_bed_price',
    ];
    public $translatable = ['name', 'address'];

    protected $casts = [
        'single_room_price' => 'float',
        'double_room_price' => 'float',
        'single_bed_price' => 'float',
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

    public function scopeActive()
    {
        return $this->where('is_active', true);
    }
}