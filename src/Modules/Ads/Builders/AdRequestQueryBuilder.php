<?php

namespace Modules\Ads\Builders;

use Illuminate\Database\Eloquent\Builder;

class AdRequestQueryBuilder extends Builder
{
    public function pendingPayment(): self
    {
        return $this->where('status', 'pending_payment');
    }

    public function approved(): self
    {
        return $this->where('status', 'approved');
    }
}
