<?php

namespace Modules\Memberships\Models;

use Modules\Core\Models\CustomModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MembershipRequest extends CustomModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'specialty',
        'degree',
        'registration_number',
        'delivery_method',
        'address',
        'status',
        'printing_cost',
        'delivery_cost',
        'subscription_cost',
        'total_amount',
    ];

    protected $casts = [
        'address' => 'array',
        'printing_cost' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'subscription_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function newEloquentBuilder($query): \Modules\Memberships\Builders\MembershipRequestQueryBuilder
    {
        return new \Modules\Memberships\Builders\MembershipRequestQueryBuilder($query);
    }
}
