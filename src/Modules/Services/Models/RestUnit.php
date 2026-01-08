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
    ];
    public $translatable = ['name', 'address'];

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