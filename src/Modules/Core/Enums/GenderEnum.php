<?php

namespace Modules\Core\Enums;

enum GenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::MALE => __('gender.male'),
            self::FEMALE => __('gender.female'),
        };
    }

    public static function labelFor(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $case = self::tryFrom($value);

        return $case?->label() ?? $value;
    }
}
