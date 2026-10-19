<?php

namespace Modules\Notifications\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminNotification extends Model
{
    public const CHANNEL_FCM = 'fcm';

    public const CHANNEL_SMS = 'sms';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_USERS = 'users';

    public const AUDIENCE_REGISTRATION_NUMBERS = 'registration_numbers';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title',
        'body',
        'channels',
        'audience',
        'user_ids',
        'registration_numbers',
        'status',
        'recipients_count',
        'fcm_sent_count',
        'fcm_failed_count',
        'sms_sent_count',
        'sms_failed_count',
        'created_by',
        'batch_id',
        'queued_at',
        'completed_at',
    ];

    protected $casts = [
        'channels' => 'array',
        'user_ids' => 'array',
        'registration_numbers' => 'array',
        'recipients_count' => 'integer',
        'fcm_sent_count' => 'integer',
        'fcm_failed_count' => 'integer',
        'sms_sent_count' => 'integer',
        'sms_failed_count' => 'integer',
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function usesChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
