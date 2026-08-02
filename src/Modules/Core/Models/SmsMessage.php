<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $fillable = [
        'provider',
        'type',
        'message_id',
        'sender',
        'receiver',
        'message',
        'status',
        'provider_status',
        'response_status_code',
        'response_body',
        'metadata',
        'dlr_payload',
        'sent_at',
        'delivered_at',
        'failed_at',
        'last_status_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'dlr_payload' => 'array',
        'response_status_code' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_status_at' => 'datetime',
    ];
}
