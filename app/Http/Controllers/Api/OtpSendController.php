<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendOtpRequest;
use App\Http\Requests\Api\VerityOtpRequest;
use Illuminate\Http\Request;
use Modules\Core\Enums\OtpEnum;
use Modules\Core\Services\OtpService;
use Modules\Users\Models\User;

class OtpSendController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    )
    {
    }

    public function send(SendOtpRequest $request)
    {
        if (User::where(['phone' => $request->phone, 'active' => true])->exists() && $request->action && $request->action == OtpEnum::REGISTER->value) {
            return response()->json([
                'status' => false,
                'message' => __('Phone number already exists.')
            ], 422);
        }

        if (!User::where('phone', $request->phone)->exists() && $request->action && $request->action == OtpEnum::FORGET->value) {
            return response()->json([
                'status' => false,
                'message' => __('Phone number not found.')
            ], 422);
        }

        $this->otpService->generatePhoneOtp($request->phone, $request->input('action') ?? OtpEnum::REGISTER->value);

        return response()->json([
            'message' => __('OTP sent to your phone.'),
            'status' => 200,
        ], 200);
    }

    public function verify(VerityOtpRequest $request)
    {
        $res = $this->otpService->verifyPhoneOtp($request->phone, $request->code, $request->input('action') ?? OtpEnum::REGISTER->value);
        if ($res) {
            if ($request->input('action') && $request->input('action') == OtpEnum::REGISTER->value) {
                // Activate user
                $user = User::where('phone', $request->phone)->first();
                if ($user) {
                    $user->active = true;
                    $user->save();
                }
            }

            return response()->json([
                'message' => __('Phone number verified successfully.'),
                'verified' => true,
                'status' => 200,
            ]);
        }

        return response()->json([
            'message' => __('Invalid OTP code.'),
            'verified' => false,
        ], 422);
    }

    public function resend(SendOtpRequest $request)
    {
        $this->otpService->generatePhoneOtp($request->phone, $request->input('action') ?? OtpEnum::REGISTER->value);

        return response()->json([
            'message' => __('OTP resent to your phone.'),
        ]);
    }
}
