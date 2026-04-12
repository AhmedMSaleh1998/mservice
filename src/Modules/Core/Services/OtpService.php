<?php

namespace Modules\Core\Services;

use App\Services\Sms\CommunitySmsService;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Otp;

class OtpService
{
    public function __construct(
        private readonly CommunitySmsService $communitySmsService,
    ) {
    }

    public function generatePhoneOtp(string $phone, string $action): Otp
    {
        $existingOtp = Otp::query()->findValidByPhone($phone, $action)->first();

        if ($existingOtp && $existingOtp->created_at->addMinute()->isAfter(now())) {
            throw ValidationException::withMessages([
                'phone' => [__('auth.otp_throttle')]
            ]);
        }


        // Invalidate any existing OTPS for this phone
        Otp::query()
            ->where('phone', $phone)
            ->where('action', $action)
            ->update(['expired_at' => now()]);

        $code = config('app.otp_mode') == 'test' ? '111111' : str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = now()->addMinutes(10);

        $otp = Otp::create([
            'phone' => $phone,
            'code' => $code,
            'action' => $action,
            'expired_at' => $expiredAt,
            'attempts' => 0,
        ]);

        try {
            $this->sendSmsOtp($phone, $code, $action);
        } catch (\Throwable $exception) {
            $otp->update(['expired_at' => now()]);

            throw $exception;
        }

        return $otp;
    }

    public function verifyPhoneOtp(string $phone, string $code, string $action): bool
    {
        $otp = Otp::where('phone', $phone)
            ->where('code', $code)
            ->where('action', $action)
            ->where('expired_at', '>', now())
            ->where('verified_at', null)
            ->where('attempts', '<', 5)
            ->first();

        if (!$otp) {
            $existingOtp = Otp::where('phone', $phone)
                ->where('action', $action)
                ->where('expired_at', '>', now())
                ->first();
            if ($existingOtp) {
                $existingOtp->incrementAttempts();
            }
            return false;
        }

        $otp->verify();
        return true;
    }

    public function resendPhoneOtp(string $phone, string $action): Otp
    {
        return $this->generatePhoneOtp($phone, $action);
    }

    private function sendSmsOtp(string $phone, string $code, string $action): void
    {
        if (config('app.otp_mode') === 'test') {
            return;
        }

        $this->communitySmsService->sendOtp($phone, $code, [
            'metadata' => [
                'action' => $action,
            ],
        ]);
    }
}
