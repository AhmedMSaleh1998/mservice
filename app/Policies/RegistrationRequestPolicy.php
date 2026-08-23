<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RegistrationRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegistrationRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RegistrationRequest');
    }

    public function view(AuthUser $authUser, RegistrationRequest $registrationRequest): bool
    {
        return $authUser->can('View:RegistrationRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RegistrationRequest');
    }

    public function update(AuthUser $authUser, RegistrationRequest $registrationRequest): bool
    {
        return $authUser->can('Update:RegistrationRequest');
    }

    public function delete(AuthUser $authUser, RegistrationRequest $registrationRequest): bool
    {
        return $authUser->can('Delete:RegistrationRequest');
    }

}