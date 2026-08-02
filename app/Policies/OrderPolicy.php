<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Order');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Order');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Order');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Order');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Order');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Order');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Order');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Order');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Order');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Order');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Order');
    }
}
