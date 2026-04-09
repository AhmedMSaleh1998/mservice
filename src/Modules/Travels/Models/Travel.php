<?php

namespace Modules\Travels\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Province;
use Modules\Travels\Builders\TravelQueryBuilder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Travel extends CustomModel implements HasMedia
{
    use HasTranslations, InteractsWithMedia, SoftDeletes;

    protected $table = 'travels';

    protected $fillable = [
        'title',
        'description',
        'location',
        'province_id',
        'meeting_point_title',
        'meeting_point_description',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public $translatable = [
        'title',
        'description',
        'location',
        'meeting_point_title',
        'meeting_point_description',
    ];

    public function newEloquentBuilder($query): TravelQueryBuilder
    {
        return new TravelQueryBuilder($query);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TravelCategory::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TravelBooking::class);
    }
}
