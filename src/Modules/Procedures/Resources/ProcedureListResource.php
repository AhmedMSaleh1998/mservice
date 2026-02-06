<?php

namespace Modules\Procedures\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\CustomResource;

class ProcedureListResource extends CustomResource
{
    public function data(Request $request): array
    {
        $iconPath = $this->resource->icon_path;
        $iconUrl = null;

        if ($iconPath) {
            $iconUrl = Storage::disk('public')->url($iconPath);
        }

        return [
            'title' => $this->resource->title,
            'icon_url' => $iconUrl,
        ];
    }
}
