<?php

namespace Modules\Procedures\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Procedure extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'required_documents',
        'steps',
        'conditions',
        'file_path',
        'icon_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public array $translatable = [
        'title',
        'required_documents',
        'steps',
        'conditions',
    ];
}
