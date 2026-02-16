<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\MedicalGuide\Models\MedicalGuide;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicalGuidePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicalGuide');
    }

    public function view(AuthUser $authUser, MedicalGuide $medicalGuide): bool
    {
        return $authUser->can('View:MedicalGuide');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicalGuide');
    }

    public function update(AuthUser $authUser, MedicalGuide $medicalGuide): bool
    {
        return $authUser->can('Update:MedicalGuide');
    }

    public function delete(AuthUser $authUser, MedicalGuide $medicalGuide): bool
    {
        return $authUser->can('Delete:MedicalGuide');
    }

    public function restore(AuthUser $authUser, MedicalGuide $medicalGuide): bool
    {
        return $authUser->can('Restore:MedicalGuide');
    }

    public function forceDelete(AuthUser $authUser, MedicalGuide $medicalGuide): bool
    {
        return $authUser->can('ForceDelete:MedicalGuide');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicalGuide');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicalGuide');
    }

    public function replicate(AuthUser $authUser, MedicalGuide $medicalGuide): bool
    {
        return $authUser->can('Replicate:MedicalGuide');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicalGuide');
    }

}