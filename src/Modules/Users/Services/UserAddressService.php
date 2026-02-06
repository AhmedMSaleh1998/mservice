<?php

namespace Modules\Users\Services;

use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;

class UserAddressService
{
    public function create(User $user, array $data): UserAddress
    {
        return $user->addresses()->create($data);
    }

    public function update(User $user, UserAddress $address, array $data): UserAddress
    {
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $address->fill($data);
        $address->save();

        return $address->fresh();
    }
}
