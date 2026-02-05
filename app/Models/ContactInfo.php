<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ContactInfo extends Model
{
    use HasTranslations;

    protected $fillable = [
        'address',
        'email',
        'phones',
        'fax',
    ];

    protected $casts = [
        'phones' => 'array',
    ];

    public array $translatable = ['address'];
}
