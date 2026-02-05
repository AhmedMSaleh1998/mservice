<?php

namespace Modules\Support\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class SupportTicketResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'created_at' => optional($this->resource->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
