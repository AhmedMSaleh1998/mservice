<?php

namespace Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->original_url ? (str_starts_with($this->original_url, 'http') ? $this->original_url : asset($this->original_url)) : null,
        ];
    }
}
