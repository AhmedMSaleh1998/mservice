<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendOtpRequest;
use App\Http\Requests\Api\VerityOtpRequest;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Log;
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
        $normalizedPhone = PhoneNumberNormalizer::normalize($request->phone);
        $matchingUser = User::whereIn('phone', $phoneVariants)
            ->latest('id')
            ->first();

        Log::info('OTP send request received.', [
            'action' => $action,
            'raw_action' => $request->input('action'),
            'normalized_phone' => $normalizedPhone,
            'phone_variants' => $phoneVariants,
            'matching_user_id' => $matchingUser?->id,
            'matching_user_active' => $matchingUser?->active,
        ]);

        if ($matchingUser?->active && $request->action && $action === OtpEnum::REGISTER->value) {
            Log::info('OTP send rejected because phone already belongs to an active user.', [
                'action' => $action,
                'normalized_phone' => $normalizedPhone,
                'matching_user_id' => $matchingUser->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => __('Phone number already exists.')
            ], 422);
        }

        if (! ($matchingUser?->active) && $action === OtpEnum::FORGET->value) {
            Log::info('OTP send rejected because no active user was found for forgot password.', [
                'action' => $action,
                'normalized_phone' => $normalizedPhone,
                'matching_user_id' => $matchingUser?->id,
                'matching_user_active' => $matchingUser?->active,
            ]);

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

                // Refresh the official name from Oracle right away so the
                // account starts with syndicate data; never block activation.
                try {
                    app(\App\Services\OracleNameSyncService::class)->syncUser($user);
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::warning('Oracle name sync at activation failed.', [
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }

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
        $normalizedPhone = PhoneNumberNormalizer::normalize($request->phone);
        $matchingUser = User::whereIn('phone', $phoneVariants)
            ->latest('id')
            ->first();

        Log::info('OTP resend request received.', [
            'action' => $action,
            'raw_action' => $request->input('action'),
            'normalized_phone' => $normalizedPhone,
            'phone_variants' => $phoneVariants,
            'matching_user_id' => $matchingUser?->id,
            'matching_user_active' => $matchingUser?->active,
        ]);

        if (! ($matchingUser?->active) && $action === OtpEnum::FORGET->value) {
            Log::info('OTP resend rejected because no active user was found for forgot password.', [
                'action' => $action,
                'normalized_phone' => $normalizedPhone,
                'matching_user_id' => $matchingUser?->id,
                'matching_user_active' => $matchingUser?->active,
            ]);

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
