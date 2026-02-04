<?php

namespace Modules\Ads\Builders;

use Illuminate\Database\Eloquent\Builder;

class AdSpaceQueryBuilder extends Builder
{
    public function available(): self
    {
        return $this->where('is_available', true);
    }
}
