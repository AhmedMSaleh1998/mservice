<?php

namespace Modules\Users\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

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
    use HasApiTokens, InteractsWithMedia , HasRoles;

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
        'oracle_synced_at' => 'datetime',
    ];

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Core\Models\Order::class);
    }

    public function restUnitBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Services\Models\RestUnitBooking::class);
    }

    public function certificateRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Certificates\Models\CertificateRequest::class);
    }

    public function supportTickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Support\Models\SupportTicket::class);
    }

    public function adRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Ads\Models\AdRequest::class);
    }

    public function courseBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Courses\Models\CourseBooking::class);
    }

    public function travelBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Travels\Models\TravelBooking::class);
    }
}
