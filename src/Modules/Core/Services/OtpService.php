<?php

namespace Modules\Core\Services;

use App\Services\Sms\CommunitySmsService;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Log;
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

        Log::info('OTP generation started.', [
            'action' => $action,
            'normalized_phone' => $normalizedPhone,
            'phone_variants' => $phoneVariants,
        ]);

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

            Log::info('OTP generation throttled.', [
                'action' => $action,
                'normalized_phone' => $normalizedPhone,
                'existing_otp_id' => $existingOtp->id,
                'retry_after_seconds' => $seconds,
            ]);

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

        Log::info('OTP record created.', [
            'otp_id' => $otp->id,
            'action' => $action,
            'normalized_phone' => $normalizedPhone,
            'expires_at' => $expiredAt?->toDateTimeString(),
        ]);

        try {
            $this->sendSmsOtp($normalizedPhone, $code, $action);
        } catch (\Throwable $exception) {
            $otp->update(['expired_at' => now()]);

            Log::warning('OTP SMS dispatch failed; OTP expired.', [
                'otp_id' => $otp->id,
                'action' => $action,
                'normalized_phone' => $normalizedPhone,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('OTP generation completed and SMS dispatch succeeded.', [
            'otp_id' => $otp->id,
            'action' => $action,
            'normalized_phone' => $normalizedPhone,
        ]);

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
            Log::info('OTP SMS dispatch skipped because app.otp_mode is test.', [
                'action' => $action,
                'normalized_phone' => PhoneNumberNormalizer::normalize($phone),
            ]);

            return;
        }

        Log::info('OTP SMS dispatch started.', [
            'action' => $action,
            'normalized_phone' => PhoneNumberNormalizer::normalize($phone),
            'sms_enabled' => $this->communitySmsService->isEnabled(),
        ]);

        $this->communitySmsService->sendOtp($phone, $code, [
            'metadata' => [
                'action' => $action,
            ],
        ]);
    }
}
