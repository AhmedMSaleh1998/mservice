<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\CustomModel;
use Modules\Services\Builders\ServiceTypeQueryBuilder;
use Spatie\Translatable\HasTranslations;

class ServiceType extends CustomModel
{
    use HasTranslations;

    protected $fillable = ['name'];

    public $translatable = ['name'];

    public function newEloquentBuilder($query): ServiceTypeQueryBuilder
    {
        return new ServiceTypeQueryBuilder($query);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
