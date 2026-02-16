<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Ads\Models\AdRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdRequest');
    }

    public function view(AuthUser $authUser, AdRequest $adRequest): bool
    {
        return $authUser->can('View:AdRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdRequest');
    }

    public function update(AuthUser $authUser, AdRequest $adRequest): bool
    {
        return $authUser->can('Update:AdRequest');
    }

    public function delete(AuthUser $authUser, AdRequest $adRequest): bool
    {
        return $authUser->can('Delete:AdRequest');
    }

    public function restore(AuthUser $authUser, AdRequest $adRequest): bool
    {
        return $authUser->can('Restore:AdRequest');
    }

    public function forceDelete(AuthUser $authUser, AdRequest $adRequest): bool
    {
        return $authUser->can('ForceDelete:AdRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdRequest');
    }

    public function replicate(AuthUser $authUser, AdRequest $adRequest): bool
    {
        return $authUser->can('Replicate:AdRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdRequest');
    }

}