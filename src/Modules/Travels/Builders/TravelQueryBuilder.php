<?php

namespace Modules\Travels\Builders;

use Illuminate\Database\Eloquent\Builder;

class TravelQueryBuilder extends Builder
{
    public function active(): self
    {
        return $this->where('is_active', true);
    }

    public function upcoming(): self
    {
        return $this->whereDate('end_date', '>=', now()->toDateString());
    }
}
