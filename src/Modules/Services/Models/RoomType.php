<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class RoomType extends Model
{
    use HasTranslations, SoftDeletes;

    protected $table = 'room_types';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    public $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(RestUnitRoom::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
