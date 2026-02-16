<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Services\Models\RestUnit;
use Illuminate\Auth\Access\HandlesAuthorization;

class RestUnitPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RestUnit');
    }

    public function view(AuthUser $authUser, RestUnit $restUnit): bool
    {
        return $authUser->can('View:RestUnit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RestUnit');
    }

    public function update(AuthUser $authUser, RestUnit $restUnit): bool
    {
        return $authUser->can('Update:RestUnit');
    }

    public function delete(AuthUser $authUser, RestUnit $restUnit): bool
    {
        return $authUser->can('Delete:RestUnit');
    }

    public function restore(AuthUser $authUser, RestUnit $restUnit): bool
    {
        return $authUser->can('Restore:RestUnit');
    }

    public function forceDelete(AuthUser $authUser, RestUnit $restUnit): bool
    {
        return $authUser->can('ForceDelete:RestUnit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RestUnit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RestUnit');
    }

    public function replicate(AuthUser $authUser, RestUnit $restUnit): bool
    {
        return $authUser->can('Replicate:RestUnit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RestUnit');
    }

}