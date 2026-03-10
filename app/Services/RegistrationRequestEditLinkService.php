<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Modules\Users\Models\RegistrationRequest;

class RegistrationRequestEditLinkService
{
    public function portalEditUrl(RegistrationRequest $registrationRequest, string $locale = 'ar'): string
    {
        $plainToken = Str::random(64);

        $registrationRequest->forceFill([
            'edit_link_token' => hash('sha256', $plainToken),
            'edit_link_sent_at' => now(),
            'edit_link_opened_at' => null,
        ])->save();

        return URL::signedRoute('portal.register.edit', [
            'reg_code' => $registrationRequest->reg_code,
            'lang' => $locale,
            'token' => $plainToken,
        ]);
    }

    public function apiUpdateUrl(RegistrationRequest $registrationRequest): string
    {
        return URL::signedRoute('register-request.update', [
            'reg_code' => $registrationRequest->reg_code,
        ]);
    }

    public function consumePortalEditLink(RegistrationRequest $registrationRequest, ?string $plainToken): bool
    {
        if (! is_string($plainToken) || $plainToken === '') {
            return false;
        }

        $storedToken = $registrationRequest->edit_link_token;
        if (! is_string($storedToken) || $storedToken === '') {
            return false;
        }

        $hashedToken = hash('sha256', $plainToken);
        if (! hash_equals($storedToken, $hashedToken)) {
            return false;
        }

        if ($registrationRequest->edit_link_opened_at !== null) {
            return false;
        }

        return RegistrationRequest::query()
            ->whereKey($registrationRequest->getKey())
            ->where('edit_link_token', $hashedToken)
            ->whereNull('edit_link_opened_at')
            ->update([
                'edit_link_opened_at' => now(),
                'edit_link_token' => null,
            ]) === 1;
    }
}
