<?php

namespace App\Http\Controllers\Api;

use App\Events\PhoneChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePhoneRequest;
use App\Http\Requests\Api\VerityOtpRequest;
use Modules\Core\Enums\OtpEnum;
use Modules\Core\Services\AuditLogger;
use Modules\Core\Services\OtpService;

class ChangePhoneController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    )
    {
    }

    public function change(ChangePhoneRequest $request)
    {
        event(new PhoneChangedEvent(auth()->user(), $request->new_phone));
        return response()->json([
            'message' => __('Verification Code Send Successfully'),
            'status' => 200,
        ], 200);
    }

    public function verify(VerityOtpRequest $request)
    {
        $user = auth()->user();
        $res = $this->otpService->verifyPhoneOtp($request->phone, $request->code, $request->input('action') ?? OtpEnum::CHANGE_PHONE->value);
        if ($res) {
            $oldPhone = $user->phone;
            $user->phone = $request->phone;
            $user->save();

            AuditLogger::log(
                OtpEnum::CHANGE_PHONE->value,
                ['phone' => $oldPhone],
                ['phone' => $user->phone]
            );

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
}
