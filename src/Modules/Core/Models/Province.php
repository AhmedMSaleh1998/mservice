<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Province extends Model
{
    use HasTranslations;

    protected $table = 'provinces';
    protected $fillable = [
        'code',
        'name',
        'shipping_cost',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
    ];

    public $translatable = ['name'];
}
