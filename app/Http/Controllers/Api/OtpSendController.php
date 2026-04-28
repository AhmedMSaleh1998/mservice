<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendOtpRequest;
use App\Http\Requests\Api\VerityOtpRequest;
use App\Support\PhoneNumberNormalizer;
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
        $phoneVariants = PhoneNumberNormalizer::variants($request->phone);
        $action = OtpEnum::normalizeAction($request->input('action'), OtpEnum::REGISTER->value);

        if (User::whereIn('phone', $phoneVariants)->where('active', true)->exists() && $request->action && $action === OtpEnum::REGISTER->value) {
            return response()->json([
                'status' => false,
                'message' => __('Phone number already exists.')
            ], 422);
        }

        if (!User::whereIn('phone', $phoneVariants)->where('active', true)->exists() && $action === OtpEnum::FORGET->value) {
            return response()->json([
                'status' => false,
                'message' => __('Phone number not found.')
            ], 422);
        }

        $this->otpService->generatePhoneOtp($request->phone, $action);

        return response()->json([
            'message' => __('OTP sent to your phone.'),
            'status' => 200,
        ], 200);
    }

    public function verify(VerityOtpRequest $request)
    {
        $action = OtpEnum::normalizeAction($request->input('action'), OtpEnum::REGISTER->value);
        $res = $this->otpService->verifyPhoneOtp($request->phone, $request->code, $action);
        if ($res) {
            if ($request->input('action') && $action === OtpEnum::REGISTER->value) {
                $phoneVariants = PhoneNumberNormalizer::variants($request->phone);

                // Activate user
                $user = User::whereIn('phone', $phoneVariants)->orderByDesc('id')->first();
                $user->active = true;
                $user->save();

                // delete previous phone
                User::whereIn('phone', $phoneVariants)
                    ->where('active', false)
                    ->where('role_id', 3)
                    ->delete();
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
        $phoneVariants = PhoneNumberNormalizer::variants($request->phone);
        $action = OtpEnum::normalizeAction($request->input('action'), OtpEnum::REGISTER->value);

        if (!User::whereIn('phone', $phoneVariants)->where('active', true)->exists() && $action === OtpEnum::FORGET->value) {
            return response()->json([
                'status' => false,
                'message' => __('Phone number not found.')
            ], 422);
        }

        $this->otpService->generatePhoneOtp($request->phone, $action);

        return response()->json([
            'message' => __('OTP resent to your phone.'),
        ]);
    }
}
