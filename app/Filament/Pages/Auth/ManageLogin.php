<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;

class ManageLogin extends Login
{
    protected array $extraBodyAttributes = [
        'class' => 'manage-login',
    ];
}
