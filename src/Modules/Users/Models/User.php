<?php

namespace Modules\Users\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $lang
 * @property boolean $notification_enabled
 * @property boolean $active
 * @property mixed $role_id
 * @property string $reg_number
 * @property string $national_id
 * @property string $email
 * @property string $phone
 * @property string $name
 * @property string $password
 * @property string|null $address
 * @property string|null $neqaba_address
 */
class User extends Authenticatable implements  HasMedia
{
    use HasApiTokens, InteractsWithMedia;

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'national_id', 'reg_number', 'role_id', 'active', 'lang', 'notification_enabled',
        'address', 'neqaba_address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'active' => 'boolean',
        'notification_enabled' => 'boolean',
    ];
}