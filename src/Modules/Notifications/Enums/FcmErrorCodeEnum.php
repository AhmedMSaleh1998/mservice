<?php

namespace Modules\Notifications\Enums;

use Kreait\Firebase\Exception\MessagingException;
use Throwable;

/** FCM v1 `errorCode` values returned for a failed send. */
enum FcmErrorCodeEnum: string
{
    case UNSPECIFIED_ERROR = 'UNSPECIFIED_ERROR';
    case INVALID_ARGUMENT = 'INVALID_ARGUMENT';
    case UNREGISTERED = 'UNREGISTERED';
    case SENDER_ID_MISMATCH = 'SENDER_ID_MISMATCH';
    case QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';
    case UNAVAILABLE = 'UNAVAILABLE';
    case INTERNAL = 'INTERNAL';
    case THIRD_PARTY_AUTH_ERROR = 'THIRD_PARTY_AUTH_ERROR';

    public static function fromThrowable(?Throwable $error): ?self
    {
        if (! $error instanceof MessagingException) {
            return null;
        }

        foreach ((array) data_get($error->errors(), 'error.details', []) as $detail) {
            if ($code = self::tryFrom((string) data_get($detail, 'errorCode'))) {
                return $code;
            }
        }

        return null;
    }

    /**
     * The token will never receive a push from this Firebase project again:
     * the app was uninstalled, or the token belongs to another project.
     */
    public function tokenIsUnusable(): bool
    {
        return match ($this) {
            self::UNREGISTERED, self::SENDER_ID_MISMATCH => true,
            default => false,
        };
    }
}
