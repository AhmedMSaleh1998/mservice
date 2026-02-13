<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class NewRegisterResource extends CustomResource
{
    public function data(Request $request): array
    {
        $pdfUrl = null;
        if ($this->resource->reg_code) {
            $pdfUrl = route('register-pdf', [
                'reg_code' => $this->resource->reg_code,
            ]);
        }

        return [
            'id' => $this->resource->id,
            'reg_code' => $this->resource->reg_code,
            'pdf_url' => $pdfUrl,
        ];
    }
}
