<?php

namespace Modules\Memberships\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Users\Models\UserAddress;

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
        'user_address_id',
        'payment_method'
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

    public function userAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }
}
