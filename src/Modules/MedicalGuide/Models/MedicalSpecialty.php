<?php

namespace Modules\MedicalGuide\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\MedicalGuide\Builders\MedicalSpecialtyBuilder;
use Spatie\Translatable\HasTranslations;

class MedicalSpecialty extends CustomModel
{
    use HasTranslations, SoftDeletes;

    protected $table = 'medical_specialties';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $translatable = ['name'];

    public function newEloquentBuilder($query): MedicalSpecialtyBuilder
    {
        return new MedicalSpecialtyBuilder($query);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(MedicalGuide::class, 'specialty_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
