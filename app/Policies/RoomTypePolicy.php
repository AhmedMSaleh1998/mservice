<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Services\Models\RoomType;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RoomType');
    }

    public function view(AuthUser $authUser, RoomType $roomType): bool
    {
        return $authUser->can('View:RoomType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RoomType');
    }

    public function update(AuthUser $authUser, RoomType $roomType): bool
    {
        return $authUser->can('Update:RoomType');
    }

    public function delete(AuthUser $authUser, RoomType $roomType): bool
    {
        return $authUser->can('Delete:RoomType');
    }

    public function restore(AuthUser $authUser, RoomType $roomType): bool
    {
        return $authUser->can('Restore:RoomType');
    }

    public function forceDelete(AuthUser $authUser, RoomType $roomType): bool
    {
        return $authUser->can('ForceDelete:RoomType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RoomType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RoomType');
    }

    public function replicate(AuthUser $authUser, RoomType $roomType): bool
    {
        return $authUser->can('Replicate:RoomType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RoomType');
    }
}
