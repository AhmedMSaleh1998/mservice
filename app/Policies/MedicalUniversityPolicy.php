<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Core\Models\MedicalUniversity;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicalUniversityPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicalUniversity');
    }

    public function view(AuthUser $authUser, MedicalUniversity $medicalUniversity): bool
    {
        return $authUser->can('View:MedicalUniversity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicalUniversity');
    }

    public function update(AuthUser $authUser, MedicalUniversity $medicalUniversity): bool
    {
        return $authUser->can('Update:MedicalUniversity');
    }

    public function delete(AuthUser $authUser, MedicalUniversity $medicalUniversity): bool
    {
        return $authUser->can('Delete:MedicalUniversity');
    }

    public function restore(AuthUser $authUser, MedicalUniversity $medicalUniversity): bool
    {
        return $authUser->can('Restore:MedicalUniversity');
    }

    public function forceDelete(AuthUser $authUser, MedicalUniversity $medicalUniversity): bool
    {
        return $authUser->can('ForceDelete:MedicalUniversity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicalUniversity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicalUniversity');
    }

    public function replicate(AuthUser $authUser, MedicalUniversity $medicalUniversity): bool
    {
        return $authUser->can('Replicate:MedicalUniversity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicalUniversity');
    }

}