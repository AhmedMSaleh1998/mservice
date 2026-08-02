<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RestUnitListCollection extends ResourceCollection
{
    public $collects = RestUnitListResource::class;

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'links' => $default['links'] ?? [],
        ];
    }
}
