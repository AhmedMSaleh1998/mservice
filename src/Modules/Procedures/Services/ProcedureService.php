<?php

namespace Modules\Procedures\Services;

use Modules\Procedures\Models\Procedure;

class ProcedureService
{
    public function listActive(int $limit = 100)
    {
        return Procedure::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function findActiveOrFail(Procedure $procedure): Procedure
    {
        if (! $procedure->is_active) {
            abort(404);
        }

        return $procedure;
    }
}
