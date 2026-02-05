<?php

namespace Modules\MedicalGuide\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\MedicalGuide\Builders\MedicalGuidePlaceBuilder;
use Spatie\Translatable\HasTranslations;

class MedicalGuidePlace extends CustomModel
{
    use HasTranslations, SoftDeletes;

    protected $table = 'medical_guide_places';

    protected $fillable = [
        'medical_guide_id',
        'name',
        'address',
        'lat',
        'lng',
        'phones',
        'is_active',
    ];

    protected $casts = [
        'phones' => 'array',
        'is_active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public $translatable = ['name', 'address'];

    public function newEloquentBuilder($query): MedicalGuidePlaceBuilder
    {
        return new MedicalGuidePlaceBuilder($query);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(MedicalGuide::class, 'medical_guide_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
