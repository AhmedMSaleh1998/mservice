<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Models\DeviceToken;
use Modules\Users\Models\User;

class DeviceTokenService
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    /**
     * A token identifies one app install: when another account signs in on
     * the same device, the token moves to that account.
     */
    public function register(User $user, string $token, ?string $platform = null, ?string $deviceId = null): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'device_id' => $deviceId ?: null,
                'last_used_at' => now(),
            ],
        );
    }

    public function remove(User $user, string $token): void
    {
        $user->deviceTokens()->where('token', $token)->delete();
    }
}
