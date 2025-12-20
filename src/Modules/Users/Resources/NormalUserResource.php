<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;

class NormalUserResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'national_id' => $this->resource->national_id,
            'reg_number' => $this->resource->reg_number,
            'address' => $this->resource->address,
            'neqaba_address' => $this->resource->neqaba_address,
            'photo' => MediaResource::make($this->resource->getMedia('photo')->last() ?? []),
            'settings' => [
                'lang' => $this->resource->lang,
                'notification_enabled' => $this->resource->notification_enabled,
            ],
        ];
    }
}