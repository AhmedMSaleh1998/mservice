<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;
use Modules\Memberships\Services\MembershipService;

class NormalUserResource extends CustomResource
{
    public function data(Request $request): array
    {
        $membershipProfile = app(MembershipService::class)->buildProfileSnapshot($this->resource);
        $oracleProfile = $membershipProfile['oracle_profile'] ?? null;
        unset($membershipProfile['oracle_profile']);

        $resolvedName = trim((string) ($this->resource->name ?: ($membershipProfile['full_name'] ?? '')));

        return [
            'id' => $this->resource->id,
            'name' => $resolvedName,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'national_id' => $this->resource->national_id,
            'reg_number' => $this->resource->reg_number ?: data_get($oracleProfile, 'register_no'),
            'membership_profile' => $membershipProfile,
            'oracle_profile' => $oracleProfile,
            'address' => $this->resource->address,
            'neqaba_address' => $this->resource->neqaba_address,
            'photo' => MediaResource::make($this->resource->getMedia('photo')->last()),
            'settings' => [
                'lang' => $this->resource->lang,
                'notification_enabled' => $this->resource->notification_enabled,
            ],
        ];
    }
}
