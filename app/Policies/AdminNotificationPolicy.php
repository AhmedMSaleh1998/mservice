<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Notifications\Models\AdminNotification;

class AdminNotificationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdminNotification');
    }

    public function view(AuthUser $authUser, AdminNotification $adminNotification): bool
    {
        return $authUser->can('View:AdminNotification');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdminNotification');
    }

    public function update(AuthUser $authUser, AdminNotification $adminNotification): bool
    {
        return $authUser->can('Update:AdminNotification');
    }

    public function delete(AuthUser $authUser, AdminNotification $adminNotification): bool
    {
        return $authUser->can('Delete:AdminNotification');
    }

    public function restore(AuthUser $authUser, AdminNotification $adminNotification): bool
    {
        return $authUser->can('Restore:AdminNotification');
    }

    public function forceDelete(AuthUser $authUser, AdminNotification $adminNotification): bool
    {
        return $authUser->can('ForceDelete:AdminNotification');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdminNotification');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdminNotification');
    }

    public function replicate(AuthUser $authUser, AdminNotification $adminNotification): bool
    {
        return $authUser->can('Replicate:AdminNotification');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdminNotification');
    }
}
