<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Procedures\Models\Procedure;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcedurePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Procedure');
    }

    public function view(AuthUser $authUser, Procedure $procedure): bool
    {
        return $authUser->can('View:Procedure');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Procedure');
    }

    public function update(AuthUser $authUser, Procedure $procedure): bool
    {
        return $authUser->can('Update:Procedure');
    }

    public function delete(AuthUser $authUser, Procedure $procedure): bool
    {
        return $authUser->can('Delete:Procedure');
    }

    public function restore(AuthUser $authUser, Procedure $procedure): bool
    {
        return $authUser->can('Restore:Procedure');
    }

    public function forceDelete(AuthUser $authUser, Procedure $procedure): bool
    {
        return $authUser->can('ForceDelete:Procedure');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Procedure');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Procedure');
    }

    public function replicate(AuthUser $authUser, Procedure $procedure): bool
    {
        return $authUser->can('Replicate:Procedure');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Procedure');
    }

}