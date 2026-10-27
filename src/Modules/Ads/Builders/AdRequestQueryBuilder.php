<?php

namespace Modules\Ads\Builders;

use Illuminate\Database\Eloquent\Builder;
use Modules\Ads\Enums\AdRequestStatusEnum;

class AdRequestQueryBuilder extends Builder
{
    public function pendingPayment(): self
    {
        return $this->where('status', AdRequestStatusEnum::PENDING_PAYMENT->value);
    }

    public function approved(): self
    {
        return $this->where('status', AdRequestStatusEnum::APPROVED->value);
    }

    public function published(): self
    {
        return $this->whereIn('status', AdRequestStatusEnum::values(AdRequestStatusEnum::publishedStatuses()));
    }

    /** Approved and still inside the display period. */
    public function running(): self
    {
        return $this->approved()
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** Approved at some point but past the display period. */
    public function ended(): self
    {
        return $this->published()
            ->where(fn (Builder $query) => $query
                ->where('status', AdRequestStatusEnum::COMPLETED->value)
                ->orWhere('ends_at', '<=', now()));
    }

    public function visible(): self
    {
        return $this->where('is_visible', true);
    }

    public function forService(int $serviceId): self
    {
        return $this->whereHas('adSpace', fn (Builder $query) => $query->where('service_id', $serviceId));
    }
}
