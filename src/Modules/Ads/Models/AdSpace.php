<?php

namespace Modules\Ads\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Services\Models\Service;

class AdSpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'max_characters',
        'min_duration_months',
        'price_per_month',
        'is_available',
        'order',
    ];

    protected $casts = [
        'service_id' => 'integer',
        'max_characters' => 'integer',
        'min_duration_months' => 'integer',
        'price_per_month' => 'decimal:2',
        'is_available' => 'boolean',
        'order' => 'integer',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(AdRequest::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
