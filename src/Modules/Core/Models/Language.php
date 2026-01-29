<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Language extends Model
{
    use HasTranslations;

    protected $table = 'languages';

    protected $fillable = [
        'name',
    ];

    public $translatable = ['name'];
}
