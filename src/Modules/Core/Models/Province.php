<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Province extends Model
{
    use HasTranslations;

    protected $table = 'provinces';
    protected $fillable = [
        'name',
    ];

    public $translatable = ['name'];
}