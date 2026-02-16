<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Services\Models\ServiceType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ServiceType');
    }

    public function view(AuthUser $authUser, ServiceType $serviceType): bool
    {
        return $authUser->can('View:ServiceType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ServiceType');
    }

    public function update(AuthUser $authUser, ServiceType $serviceType): bool
    {
        return $authUser->can('Update:ServiceType');
    }

    public function delete(AuthUser $authUser, ServiceType $serviceType): bool
    {
        return $authUser->can('Delete:ServiceType');
    }

    public function restore(AuthUser $authUser, ServiceType $serviceType): bool
    {
        return $authUser->can('Restore:ServiceType');
    }

    public function forceDelete(AuthUser $authUser, ServiceType $serviceType): bool
    {
        return $authUser->can('ForceDelete:ServiceType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ServiceType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ServiceType');
    }

    public function replicate(AuthUser $authUser, ServiceType $serviceType): bool
    {
        return $authUser->can('Replicate:ServiceType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ServiceType');
    }

}