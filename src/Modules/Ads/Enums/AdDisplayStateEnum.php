<?php

namespace Modules\Ads\Enums;

/** Whether an approved ad is still inside its display period. */
enum AdDisplayStateEnum: string
{
    case RUNNING = 'running';
    case ENDED = 'ended';

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => __('Running'),
            self::ENDED => __('Ended'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RUNNING => 'success',
            self::ENDED => 'gray',
        };
    }
}
