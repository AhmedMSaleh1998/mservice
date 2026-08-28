<?php

namespace App\Services;

use App\Services\Oracle\OracleDoctorSituationService;
use Illuminate\Support\Facades\Log;
use Modules\Users\Models\User;

class DoctorSituationService
{
    public function __construct(
        private readonly OracleDoctorSituationService $oracleDoctorSituationService,
    ) {
    }

    /**
     * Whether the syndicate has an open situation (موقف) against this user.
     *
     * Fails open: a user without a registration number, or an unreachable
     * Oracle, must not lock every service for everyone.
     */
    public function userHasSituation(User $user): bool
    {
        $registerNo = trim((string) $user->reg_number);

        if ($registerNo === '') {
            return false;
        }

        try {
            return $this->oracleDoctorSituationService->doctorHasSituation($registerNo);
        } catch (\Throwable $exception) {
            Log::error('Doctor situation check failed; allowing the request.', [
                'user_id' => $user->id,
                'register_no' => $registerNo,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function blockedMessage(): string
    {
        return __('You have a pending situation with the syndicate. Please contact the syndicate to resolve it.');
    }
}
