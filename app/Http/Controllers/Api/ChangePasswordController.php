<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request)
    {
        $user = auth()->user();

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => __('New password cannot be the same as the old password.'),
                'status' => 422,
            ], 422);
        }

        $user->update([
            'password' => bcrypt($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Password changed successfully.'),
        ]);
    }
}
