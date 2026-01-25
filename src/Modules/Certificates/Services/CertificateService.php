<?php

namespace Modules\Certificates\Services;

use Modules\Certificates\Models\Certificate;

class CertificateService
{
    public function getCertificates()
    {
        return $this->baseQuery()
            ->orderBy('order')
            ->with('media')
            ->get();
    }

    protected function baseQuery()
    {
        return Certificate::query()->where('is_active', true);
    }

}