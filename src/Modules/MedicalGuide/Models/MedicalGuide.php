<?php

namespace Modules\MedicalGuide\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\MedicalGuide\Builders\MedicalGuideBuilder;
use Modules\MedicalGuide\Models\MedicalSpecialty;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Province;

class MedicalGuide extends CustomModel implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, HasTranslations;

    protected $fillable = ['title', 'description', 'specialty_id', 'province_id', 'is_featured', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public $translatable = ['title', 'description'];

    public function newEloquentBuilder($query): MedicalGuideBuilder
    {
        return new MedicalGuideBuilder($query);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->useDisk('public');
    }

    protected static function booted()
    {
        static::saving(function (MedicalGuide $guide) {
            if (blank($guide->description) && $guide->specialty_id) {
                $specialty = $guide->specialty instanceof MedicalSpecialty
                    ? $guide->specialty
                    : MedicalSpecialty::query()->find($guide->specialty_id);

                if ($specialty instanceof MedicalSpecialty) {
                    $guide->description = $specialty->getTranslations('name');
                }
            }
        });
    }

    public function places(): HasMany
    {
        return $this->hasMany(MedicalGuidePlace::class, 'medical_guide_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(MedicalSpecialty::class, 'specialty_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
