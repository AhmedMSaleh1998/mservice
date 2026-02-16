<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Services\Models\RestUnitBooking;
use Illuminate\Auth\Access\HandlesAuthorization;

class RestUnitBookingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RestUnitBooking');
    }

    public function view(AuthUser $authUser, RestUnitBooking $restUnitBooking): bool
    {
        return $authUser->can('View:RestUnitBooking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RestUnitBooking');
    }

    public function update(AuthUser $authUser, RestUnitBooking $restUnitBooking): bool
    {
        return $authUser->can('Update:RestUnitBooking');
    }

    public function delete(AuthUser $authUser, RestUnitBooking $restUnitBooking): bool
    {
        return $authUser->can('Delete:RestUnitBooking');
    }

    public function restore(AuthUser $authUser, RestUnitBooking $restUnitBooking): bool
    {
        return $authUser->can('Restore:RestUnitBooking');
    }

    public function forceDelete(AuthUser $authUser, RestUnitBooking $restUnitBooking): bool
    {
        return $authUser->can('ForceDelete:RestUnitBooking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RestUnitBooking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RestUnitBooking');
    }

    public function replicate(AuthUser $authUser, RestUnitBooking $restUnitBooking): bool
    {
        return $authUser->can('Replicate:RestUnitBooking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RestUnitBooking');
    }

}