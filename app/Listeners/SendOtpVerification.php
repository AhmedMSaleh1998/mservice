<?php

namespace App\Listeners;

use App\Events\OtpEventInterface;
use App\Events\UserRegistered;
use Modules\Core\Enums\OtpEnum;
use Modules\Core\Services\OtpService;

class SendOtpVerification
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected OtpService $otpService
    )
    {
    }

    /**
     * Handle the event.
     */
    public function handle(OtpEventInterface $event): void
    {
        $user = $event->user;
        $type = $event?->type ?? OtpEnum::REGISTER->value;
        $phone = $event?->phone ?? $user->phone;
        $this->otpService->generatePhoneOtp($phone, $type);
    }
}
