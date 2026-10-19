<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
