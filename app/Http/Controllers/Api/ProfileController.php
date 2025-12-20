<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Users\Resources\NormalUserResource;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $user->fill($request->only([
            'neqaba_address',
            'national_id',
            'address',
            'email',
        ]));
        if ($request->has('photo')) {
            $user->addMedia($request->file('photo'))
                ->usingName(Str::slug(pathinfo($request->file('photo')->getClientOriginalName(), PATHINFO_FILENAME)))
                ->toMediaCollection('photo');
        }
        $user->save();
        $user->fresh();

        return NormalUserResource::make($user);
    }
}
