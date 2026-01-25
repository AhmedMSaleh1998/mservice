<?php

namespace Modules\Certificates\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Certificates\Builders\CertificateRequestBuilder;
use Modules\Core\Models\CustomModel;
use Modules\Users\Models\UserAddress;

class CertificateRequest extends CustomModel
{
    protected $fillable = [
        'user_id', 'certificate_id', 'delivery_method', 'user_address_id',
        'phone', 'email', 'status', 'printing_cost', 'delivery_cost', 'subscription_cost',
        'total_amount'
    ];

    protected $casts = [
        'address' => 'array',
        'printing_cost' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'subscription_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function newEloquentBuilder($query): CertificateRequestBuilder
    {
        return new CertificateRequestBuilder($query);
    }

    public function userAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class);
    }
}