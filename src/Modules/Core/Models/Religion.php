<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Religion extends Model
{
    use HasTranslations;

    protected $table = 'religions';

    protected $fillable = [
        'name',
    ];

    public $translatable = ['name'];
}
