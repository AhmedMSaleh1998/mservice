<?php

namespace Modules\Ads\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ads\Builders\AdSpaceQueryBuilder;
use Modules\Core\Models\CustomModel;
use Spatie\Translatable\HasTranslations;

class AdSpace extends CustomModel
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'width',
        'height',
        'max_characters',
        'min_duration_months',
        'price_per_month',
        'is_available',
        'order',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'max_characters' => 'integer',
        'min_duration_months' => 'integer',
        'price_per_month' => 'decimal:2',
        'is_available' => 'boolean',
        'order' => 'integer',
    ];

    public $translatable = ['name'];

    public function newEloquentBuilder($query): AdSpaceQueryBuilder
    {
        return new AdSpaceQueryBuilder($query);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AdRequest::class);
    }
}
