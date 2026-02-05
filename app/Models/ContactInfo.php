<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInfo extends Model
{
    protected $fillable = [
        'address',
        'email',
        'phones',
        'fax',
    ];

    protected $casts = [
        'phones' => 'array',
    ];
}
