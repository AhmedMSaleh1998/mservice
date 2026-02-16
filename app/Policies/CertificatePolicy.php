<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Certificates\Models\Certificate;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Certificate');
    }

    public function view(AuthUser $authUser, Certificate $certificate): bool
    {
        return $authUser->can('View:Certificate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Certificate');
    }

    public function update(AuthUser $authUser, Certificate $certificate): bool
    {
        return $authUser->can('Update:Certificate');
    }

    public function delete(AuthUser $authUser, Certificate $certificate): bool
    {
        return $authUser->can('Delete:Certificate');
    }

    public function restore(AuthUser $authUser, Certificate $certificate): bool
    {
        return $authUser->can('Restore:Certificate');
    }

    public function forceDelete(AuthUser $authUser, Certificate $certificate): bool
    {
        return $authUser->can('ForceDelete:Certificate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Certificate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Certificate');
    }

    public function replicate(AuthUser $authUser, Certificate $certificate): bool
    {
        return $authUser->can('Replicate:Certificate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Certificate');
    }

}