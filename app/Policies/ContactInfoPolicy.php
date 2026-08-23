<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ContactInfo;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContactInfoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContactInfo');
    }

    public function view(AuthUser $authUser, ContactInfo $contactInfo): bool
    {
        return $authUser->can('View:ContactInfo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContactInfo');
    }

    public function update(AuthUser $authUser, ContactInfo $contactInfo): bool
    {
        return $authUser->can('Update:ContactInfo');
    }

    public function delete(AuthUser $authUser, ContactInfo $contactInfo): bool
    {
        return $authUser->can('Delete:ContactInfo');
    }

}