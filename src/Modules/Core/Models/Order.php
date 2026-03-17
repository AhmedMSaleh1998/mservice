<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Users\Models\User;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'provider',
        'merchant_ref_num',
        'gateway_reference',
        'gateway_status',
        'checkout_url',
        'payload',
        'payment_started_at',
        'payment_last_synced_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'payment_started_at' => 'datetime',
        'payment_last_synced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }
}
