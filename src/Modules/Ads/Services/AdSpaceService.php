<?php

namespace Modules\Ads\Services;

use Modules\Ads\Models\AdSpace;
use Illuminate\Database\Eloquent\Collection;

class AdSpaceService
{
    public function listAll(): Collection
    {
        return AdSpace::query()
            ->orderBy('order')
            ->get();
    }
}
