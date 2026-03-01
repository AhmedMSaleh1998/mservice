<?php

namespace Modules\Users\Models\Concerns;

trait HasRegistrationRequestCreation
{
    private const REG_CODE_PREFIX = 'EMS';
    private const REG_CODE_MIN = 100000000000;
    private const REG_CODE_MAX = 999999999999;

    public static function bootHasRegistrationRequestCreation(): void
    {
        static::creating(function ($request): void {

            if (blank($request->reg_code) || static::query()->where('reg_code', $request->reg_code)->exists()) {
                $request->reg_code = static::generateUniqueRegCode();
            }
        });
    }

    public static function generateUniqueRegCode(): string
    {
        do {
            $code = self::REG_CODE_PREFIX . (string) random_int(self::REG_CODE_MIN, self::REG_CODE_MAX);
        } while (static::query()->where('reg_code', $code)->exists());

        return $code;
    }
}
