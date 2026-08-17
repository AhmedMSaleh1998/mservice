<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Certificates\Models\CertificateRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificateRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CertificateRequest');
    }

    public function view(AuthUser $authUser, CertificateRequest $certificateRequest): bool
    {
        return $authUser->can('View:CertificateRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CertificateRequest');
    }

    public function update(AuthUser $authUser, CertificateRequest $certificateRequest): bool
    {
        return $authUser->can('Update:CertificateRequest');
    }

    public function delete(AuthUser $authUser, CertificateRequest $certificateRequest): bool
    {
        return $authUser->can('Delete:CertificateRequest');
    }

    public function restore(AuthUser $authUser, CertificateRequest $certificateRequest): bool
    {
        return $authUser->can('Restore:CertificateRequest');
    }

    public function forceDelete(AuthUser $authUser, CertificateRequest $certificateRequest): bool
    {
        return $authUser->can('ForceDelete:CertificateRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CertificateRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CertificateRequest');
    }

    public function replicate(AuthUser $authUser, CertificateRequest $certificateRequest): bool
    {
        return $authUser->can('Replicate:CertificateRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CertificateRequest');
    }
}
