<?php

namespace Modules\Certificates\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Certificates\Builders\CertificateQueryBuilder;
use Modules\Core\Models\CustomModel;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Certificate extends CustomModel implements HasMedia
{
    use HasTranslations, SoftDeletes, InteractsWithMedia;

    protected $fillable = ['name', 'description', 'price', 'pand_id', 'is_active', 'order'];

    protected $casts = [
        'price' => 'decimal:2',
        'pand_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public $translatable = ['name', 'description'];

    public function newEloquentBuilder($query): CertificateQueryBuilder
    {
        return new CertificateQueryBuilder($query);
    }
}
