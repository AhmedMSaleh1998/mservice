<?php

namespace Modules\Core\Enums;

enum OtpEnum: string
{
    case REGISTER = 'register';
    case FORGET = 'forget';
    case CHANGE_PHONE = 'change_phone';

    public static function normalizeAction(?string $action, string $default = self::REGISTER->value): string
    {
        return match ($action) {
            self::FORGET->value, 'forgot', 'forget_password', 'forgot_password', 'reset_password' => self::FORGET->value,
            self::CHANGE_PHONE->value, 'change-phone', 'changePhone' => self::CHANGE_PHONE->value,
            self::REGISTER->value, null, '' => $default,
            default => $action,
        };
    }
}
