<?php

namespace Modules\Core\Services;

use App\Services\Sms\CommunitySmsService;
use App\Support\PhoneNumberNormalizer;
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
        $normalizedPhone = PhoneNumberNormalizer::normalize($phone);
        $phoneVariants = PhoneNumberNormalizer::variants($phone);

        $existingOtp = Otp::query()
            ->whereIn('phone', $phoneVariants)
            ->where('action', $action)
            ->where('expired_at', '>', now())
            ->where('verified_at', null)
            ->where('attempts', '<', 5)
            ->latest()
            ->first();

        if ($existingOtp && $existingOtp->created_at->addMinute()->isAfter(now())) {
            $seconds = max(1, now()->diffInSeconds($existingOtp->created_at->addMinute(), false));

            throw ValidationException::withMessages([
                'phone' => [__('auth.otp_throttle', ['seconds' => $seconds])],
            ]);
        }


        // Invalidate any existing OTPS for this phone
        Otp::query()
            ->whereIn('phone', $phoneVariants)
            ->where('action', $action)
            ->update(['expired_at' => now()]);

        $code = config('app.otp_mode') == 'test' ? '111111' : str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = now()->addMinutes(10);

        $otp = Otp::create([
            'phone' => $normalizedPhone,
            'code' => $code,
            'action' => $action,
            'expired_at' => $expiredAt,
            'attempts' => 0,
        ]);

        try {
            $this->sendSmsOtp($normalizedPhone, $code, $action);
        } catch (\Throwable $exception) {
            $otp->update(['expired_at' => now()]);

            throw $exception;
        }

        return $otp;
    }

    public function verifyPhoneOtp(string $phone, string $code, string $action): bool
    {
        $phoneVariants = PhoneNumberNormalizer::variants($phone);

        $otp = Otp::whereIn('phone', $phoneVariants)
            ->where('code', $code)
            ->where('action', $action)
            ->where('expired_at', '>', now())
            ->where('verified_at', null)
            ->where('attempts', '<', 5)
            ->first();

        if (!$otp) {
            $existingOtp = Otp::whereIn('phone', $phoneVariants)
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
