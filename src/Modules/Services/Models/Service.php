<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Services\Builders\ServiceQueryBuilder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Service extends CustomModel implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, HasTranslations;

    protected $fillable = ['title', 'description', 'service_type_id', 'is_active', 'is_featured'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];
    public $translatable = ['title', 'description'];

    public function newEloquentBuilder($query): ServiceQueryBuilder
    {
        return new ServiceQueryBuilder($query);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function scopeFeatured()
    {
        return $this->where('is_featured', true);
    }
}
