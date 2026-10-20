<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Modules\MedicalGuide\Services\DoctorRecordsService;

/**
 * The signed-in doctor's own profile: the user fields plus the doctor's
 * records from the syndicate (clinics, specialties, membership status).
 */
class ProfileResource extends NormalUserResource
{
    public function data(Request $request): array
    {
        return [
            ...parent::data($request),
            ...app(DoctorRecordsService::class)->forUser($this->resource),
        ];
    }
}
