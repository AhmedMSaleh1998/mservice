<?php

namespace Modules\Travels\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TravelListCollection extends ResourceCollection
{
    public $collects = TravelListResource::class;

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'links' => $default['links'] ?? [],
        ];
    }
}
