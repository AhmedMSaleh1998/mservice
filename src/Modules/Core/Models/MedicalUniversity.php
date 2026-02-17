<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MedicalUniversity extends Model
{
    use HasTranslations;

    protected $table = 'medical_universities';

    protected $fillable = [
        'code',
        'name',
    ];

    public $translatable = ['name'];
}
