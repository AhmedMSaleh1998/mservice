<?php

namespace Modules\Services\Services;

use Modules\Services\Models\Service;

class ServicesService
{
    /**
     * Base query builder for all service queries.
     */
    private function baseQuery()
    {
        return Service::query()
            ->with('serviceType')
            ->where('is_active', true);
    }

    /**
     * Get featured services.
     */
    public function getFeaturedServices(int $limit = 100)
    {
        return $this->baseQuery()
            ->featured()
            ->limit($limit)
            ->get();
    }

    /**
     * Get non-featured (normal) services.
     */
    public function getNormalServices(int $limit = 100)
    {
        return $this->baseQuery()
            ->where('is_featured', false)
            ->limit($limit)
            ->get();
    }

    /**
     * Get all services sorted by featured first.
     */
    public function getAllServices(int $limit = 100)
    {
        return $this->baseQuery()
            ->orderByDesc('is_featured')
            ->limit($limit)
            ->get();
    }
}
