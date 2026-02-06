<?php

namespace Modules\Procedures\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\CustomResource;

class ProcedureResource extends CustomResource
{
    public function data(Request $request): array
    {
        $filePath = $this->resource->file_path;
        $iconPath = $this->resource->icon_path;
        $fileUrl = null;
        $iconUrl = null;

        if ($filePath) {
            $fileUrl = Storage::disk('public')->url($filePath);
        }
        if ($iconPath) {
            $iconUrl = Storage::disk('public')->url($iconPath);
        }

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'required_documents' => $this->resource->required_documents,
            'steps' => $this->resource->steps,
            'conditions' => $this->resource->conditions,
            'file_url' => $fileUrl,
            'icon_url' => $iconUrl,
            'is_active' => $this->resource->is_active,
            'created_at' => optional($this->resource->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
