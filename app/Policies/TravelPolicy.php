<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Travels\Models\Travel;
use Illuminate\Auth\Access\HandlesAuthorization;

class TravelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Travel');
    }

    public function view(AuthUser $authUser, Travel $travel): bool
    {
        return $authUser->can('View:Travel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Travel');
    }

    public function update(AuthUser $authUser, Travel $travel): bool
    {
        return $authUser->can('Update:Travel');
    }

    public function delete(AuthUser $authUser, Travel $travel): bool
    {
        return $authUser->can('Delete:Travel');
    }

    public function restore(AuthUser $authUser, Travel $travel): bool
    {
        return $authUser->can('Restore:Travel');
    }

    public function forceDelete(AuthUser $authUser, Travel $travel): bool
    {
        return $authUser->can('ForceDelete:Travel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Travel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Travel');
    }

    public function replicate(AuthUser $authUser, Travel $travel): bool
    {
        return $authUser->can('Replicate:Travel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Travel');
    }
}
