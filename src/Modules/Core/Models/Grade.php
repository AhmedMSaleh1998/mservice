<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Grade extends Model
{
    use HasTranslations;

    protected $table = 'grades';

    protected $fillable = [
        'code',
        'name',
    ];

    public $translatable = ['name'];
}
