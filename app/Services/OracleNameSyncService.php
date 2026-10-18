<?php

namespace App\Services;

use App\Services\Oracle\OracleDoctorDataLookupService;
use App\Services\Oracle\OracleDoctorExistenceService;
use Illuminate\Support\Facades\Log;
use Modules\Users\Models\User;

/**
 * Verifies a user against Oracle (registration number + national ID) and
 * refreshes their name from the official record. A successful pass stamps
 * users.oracle_synced_at so the nightly sweep can skip the user; unresolved
 * cases (identity mismatch, missing Oracle record/name) stay unstamped and
 * are retried nightly until the syndicate fixes their data.
 */
class OracleNameSyncService
{
    public const OUTCOME_UPDATED = 'updated';
    public const OUTCOME_ALREADY_MATCHING = 'already_matching';
    public const OUTCOME_IDENTITY_MISMATCH = 'identity_mismatch';
    public const OUTCOME_NO_ORACLE_NAME = 'no_oracle_name';
    public const OUTCOME_MISSING_DATA = 'missing_data';

    public function __construct(
        private readonly OracleDoctorExistenceService $oracleDoctorExistenceService,
        private readonly OracleDoctorDataLookupService $oracleDoctorDataLookupService,
    ) {
    }

    /**
     * @return array{outcome: string, oracle_name: ?string}
     */
    public function syncUser(User $user, bool $apply = true): array
    {
        $registerNo = trim((string) $user->reg_number);
        $nationalId = trim((string) $user->national_id);

        if ($registerNo === '' || $nationalId === '') {
            return ['outcome' => self::OUTCOME_MISSING_DATA, 'oracle_name' => null];
        }

        // Step 1: the identity the user registered with must verify against
        // Oracle before we trust the registration number for anything.
        if (! $this->oracleDoctorExistenceService->doctorExists($registerNo, $nationalId)) {
            Log::warning('Oracle name sync: identity did not verify.', [
                'user_id' => $user->id,
                'register_no' => $registerNo,
            ]);

            return ['outcome' => self::OUTCOME_IDENTITY_MISMATCH, 'oracle_name' => null];
        }

        // Step 2: identity verified — fetch the official record.
        $profile = $this->oracleDoctorDataLookupService->findByRegisterNo($registerNo);
        $oracleName = trim((string) ($profile['doctor_name'] ?? ''));

        if ($oracleName === '') {
            Log::info('Oracle name sync: record has no name.', [
                'user_id' => $user->id,
                'register_no' => $registerNo,
            ]);

            return ['outcome' => self::OUTCOME_NO_ORACLE_NAME, 'oracle_name' => null];
        }

        if ($oracleName === trim((string) $user->name)) {
            if ($apply) {
                $user->forceFill(['oracle_synced_at' => now()])->save();
            }

            return ['outcome' => self::OUTCOME_ALREADY_MATCHING, 'oracle_name' => $oracleName];
        }

        if ($apply) {
            $user->forceFill(['name' => $oracleName, 'oracle_synced_at' => now()])->save();
            Log::info('Oracle name sync: user name updated.', [
                'user_id' => $user->id,
                'register_no' => $registerNo,
            ]);
        }

        return ['outcome' => self::OUTCOME_UPDATED, 'oracle_name' => $oracleName];
    }
}
