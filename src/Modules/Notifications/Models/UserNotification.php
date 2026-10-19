<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'admin_notification_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adminNotification(): BelongsTo
    {
        return $this->belongsTo(AdminNotification::class);
    }
}
