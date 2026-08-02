<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Core\Models\SmsMessage;

class SmsMessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmsMessage');
    }

    public function view(AuthUser $authUser, SmsMessage $smsMessage): bool
    {
        return $authUser->can('View:SmsMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmsMessage');
    }

    public function update(AuthUser $authUser, SmsMessage $smsMessage): bool
    {
        return $authUser->can('Update:SmsMessage');
    }

    public function delete(AuthUser $authUser, SmsMessage $smsMessage): bool
    {
        return $authUser->can('Delete:SmsMessage');
    }

    public function restore(AuthUser $authUser, SmsMessage $smsMessage): bool
    {
        return $authUser->can('Restore:SmsMessage');
    }

    public function forceDelete(AuthUser $authUser, SmsMessage $smsMessage): bool
    {
        return $authUser->can('ForceDelete:SmsMessage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmsMessage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmsMessage');
    }

    public function replicate(AuthUser $authUser, SmsMessage $smsMessage): bool
    {
        return $authUser->can('Replicate:SmsMessage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmsMessage');
    }
}
