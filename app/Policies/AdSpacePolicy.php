<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Ads\Models\AdSpace;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdSpacePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdSpace');
    }

    public function view(AuthUser $authUser, AdSpace $adSpace): bool
    {
        return $authUser->can('View:AdSpace');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdSpace');
    }

    public function update(AuthUser $authUser, AdSpace $adSpace): bool
    {
        return $authUser->can('Update:AdSpace');
    }

    public function delete(AuthUser $authUser, AdSpace $adSpace): bool
    {
        return $authUser->can('Delete:AdSpace');
    }

    public function restore(AuthUser $authUser, AdSpace $adSpace): bool
    {
        return $authUser->can('Restore:AdSpace');
    }

    public function forceDelete(AuthUser $authUser, AdSpace $adSpace): bool
    {
        return $authUser->can('ForceDelete:AdSpace');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdSpace');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdSpace');
    }

    public function replicate(AuthUser $authUser, AdSpace $adSpace): bool
    {
        return $authUser->can('Replicate:AdSpace');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdSpace');
    }

}