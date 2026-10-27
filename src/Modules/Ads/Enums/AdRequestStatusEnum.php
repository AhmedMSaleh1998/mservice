<?php

namespace Modules\Ads\Enums;

enum AdRequestStatusEnum: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case CHECKOUT_PENDING = 'checkout_pending';
    case PAYMENT_EXPIRED = 'payment_expired';
    case PAID_SUCCESSFULLY = 'paid_successfully';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Paid requests that never went live: still awaiting a decision, or rejected.
     *
     * @return array<self>
     */
    public static function requestStatuses(): array
    {
        return [self::PAID_SUCCESSFULLY, self::REJECTED];
    }

    /**
     * Approved ads, whether still running or already past their period.
     *
     * @return array<self>
     */
    public static function publishedStatuses(): array
    {
        return [self::APPROVED, self::COMPLETED];
    }

    /** @param  array<self>|null  $cases */
    public static function values(?array $cases = null): array
    {
        return array_map(static fn (self $case) => $case->value, $cases ?? self::cases());
    }

    /** @param  array<self>|null  $cases */
    public static function options(?array $cases = null): array
    {
        return collect($cases ?? self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => __('Pending Payment'),
            self::CHECKOUT_PENDING => __('Checkout Pending'),
            self::PAYMENT_EXPIRED => __('Payment Expired'),
            self::PAID_SUCCESSFULLY => __('Paid Successfully'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'warning',
            self::CHECKOUT_PENDING, self::PAID_SUCCESSFULLY => 'info',
            self::APPROVED, self::COMPLETED => 'success',
            self::REJECTED => 'danger',
            self::PAYMENT_EXPIRED, self::CANCELLED => 'gray',
        };
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? (string) $value;
    }

    public static function colorFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->color() ?? 'gray';
    }
}
