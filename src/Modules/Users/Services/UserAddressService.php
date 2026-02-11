<?php

namespace Modules\Users\Services;

use Illuminate\Support\Facades\DB;
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

    public function delete(User $user, UserAddress $address): bool
    {
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        if ($this->isUsedByRequests($address->id)) {
            return false;
        }

        $address->delete();

        return true;
    }

    private function isUsedByRequests(int $addressId): bool
    {
        return DB::table('membership_requests')
            ->where('user_address_id', $addressId)
            ->exists()
            || DB::table('certificate_requests')
                ->where('user_address_id', $addressId)
                ->exists();
    }
}
