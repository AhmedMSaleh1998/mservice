<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageLogin extends Login
{
    protected array $extraBodyAttributes = [
        'class' => 'manage-login',
    ];

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string | Htmlable | null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return new HtmlString(sprintf(
            '<div class="manage-login-brand"><img src="%s" alt="%s" class="manage-login-brand-logo" loading="lazy" decoding="async"></div>',
            e(asset('assets/ems-logo.png')),
            e(__('Registration Request')),
        ));
    }
}
