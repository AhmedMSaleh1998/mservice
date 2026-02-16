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

    public function restore(AuthUser $authUser, RegistrationRequest $registrationRequest): bool
    {
        return $authUser->can('Restore:RegistrationRequest');
    }

    public function forceDelete(AuthUser $authUser, RegistrationRequest $registrationRequest): bool
    {
        return $authUser->can('ForceDelete:RegistrationRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RegistrationRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RegistrationRequest');
    }

    public function replicate(AuthUser $authUser, RegistrationRequest $registrationRequest): bool
    {
        return $authUser->can('Replicate:RegistrationRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RegistrationRequest');
    }

}