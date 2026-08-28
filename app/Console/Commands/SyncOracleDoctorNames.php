<?php

namespace App\Console\Commands;

use App\Services\Oracle\OracleDoctorDataLookupService;
use App\Services\Oracle\OracleDoctorExistenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Users\Models\User;

/**
 * Reconciles user names with the syndicate's Oracle records. Each user is
 * first verified against Oracle by registration number AND national ID
 * (CHK_DOCTOR_EXIST); only a verified identity gets its name refreshed from
 * GET_DOCTOR_DATA_REGNO. Users whose identity does not verify are reported,
 * never rewritten. Oracle records without a name are reported and left alone.
 */
class SyncOracleDoctorNames extends Command
{
    protected $signature = 'users:sync-oracle-names
                            {--apply : Write the changes; without this the command only reports}
                            {--user=* : Limit the run to these user IDs}
                            {--limit=0 : Stop after processing this many users (0 = all)}';

    protected $description = 'Verify users against Oracle (registration number + national ID) and update their names from syndicate records.';

    private const MAX_CONSECUTIVE_FAILURES = 5;

    public function __construct(
        private readonly OracleDoctorExistenceService $oracleDoctorExistenceService,
        private readonly OracleDoctorDataLookupService $oracleDoctorDataLookupService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $userIds = array_filter(array_map('intval', (array) $this->option('user')));
        $limit = max(0, (int) $this->option('limit'));

        if (! $apply) {
            $this->warn('Report-only run. Pass --apply to write the changes.');
        }

        $query = User::query()
            ->whereNotNull('reg_number')->where('reg_number', '!=', '')
            ->whereNotNull('national_id')->where('national_id', '!=', '')
            ->when($userIds !== [], fn ($q) => $q->whereIn('id', $userIds))
            ->orderBy('id');

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'already_matching' => 0,
            'identity_mismatch' => 0,
            'no_oracle_name' => 0,
            'errors' => 0,
        ];
        $consecutiveFailures = 0;
        $aborted = false;

        $query->chunkById(100, function ($users) use ($apply, $limit, &$stats, &$consecutiveFailures, &$aborted): bool {
            foreach ($users as $user) {
                if ($limit > 0 && $stats['processed'] >= $limit) {
                    return false;
                }

                $stats['processed']++;

                try {
                    $this->syncUser($user, $apply, $stats);
                    $consecutiveFailures = 0;
                } catch (\Throwable $exception) {
                    $stats['errors']++;
                    $consecutiveFailures++;
                    $this->error(sprintf('#%d %s: %s', $user->id, $user->reg_number, $exception->getMessage()));
                    Log::warning('Oracle name sync failed for user.', [
                        'user_id' => $user->id,
                        'register_no' => $user->reg_number,
                        'error' => $exception->getMessage(),
                    ]);

                    if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                        $this->error(sprintf(
                            'Aborting: %d consecutive Oracle failures — the connection looks down.',
                            $consecutiveFailures,
                        ));
                        $aborted = true;

                        return false;
                    }
                }
            }

            return true;
        });

        $this->newLine();
        $this->table(
            ['Processed', 'Updated', 'Already matching', 'Identity mismatch', 'No Oracle name', 'Errors'],
            [[
                $stats['processed'],
                $stats['updated'] . ($apply ? '' : ' (would update)'),
                $stats['already_matching'],
                $stats['identity_mismatch'],
                $stats['no_oracle_name'],
                $stats['errors'],
            ]],
        );

        Log::info('Oracle name sync finished.', [...$stats, 'apply' => $apply, 'aborted' => $aborted]);

        return $aborted ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string, int> $stats
     */
    private function syncUser(User $user, bool $apply, array &$stats): void
    {
        // Step 1: the identity the user registered with must verify against
        // Oracle before we trust the registration number for anything.
        if (! $this->oracleDoctorExistenceService->doctorExists((string) $user->reg_number, (string) $user->national_id)) {
            $stats['identity_mismatch']++;
            $this->warn(sprintf(
                '#%d %s: registration number + national ID do not match any Oracle doctor — left untouched.',
                $user->id,
                $user->reg_number,
            ));
            Log::warning('Oracle name sync: identity did not verify.', [
                'user_id' => $user->id,
                'register_no' => $user->reg_number,
            ]);

            return;
        }

        // Step 2: identity verified — fetch the official record.
        $profile = $this->oracleDoctorDataLookupService->findByRegisterNo((string) $user->reg_number);
        $oracleName = trim((string) ($profile['doctor_name'] ?? ''));

        if ($oracleName === '') {
            $stats['no_oracle_name']++;
            $this->line(sprintf(
                '#%d %s: Oracle has no name for this record — left untouched.',
                $user->id,
                $user->reg_number,
            ));
            Log::info('Oracle name sync: record has no name.', [
                'user_id' => $user->id,
                'register_no' => $user->reg_number,
            ]);

            return;
        }

        if ($oracleName === trim((string) $user->name)) {
            $stats['already_matching']++;

            return;
        }

        $stats['updated']++;
        $this->info(sprintf('#%d %s: "%s" -> "%s"', $user->id, $user->reg_number, $user->name, $oracleName));

        if ($apply) {
            $user->update(['name' => $oracleName]);
            Log::info('Oracle name sync: user name updated.', [
                'user_id' => $user->id,
                'register_no' => $user->reg_number,
            ]);
        }
    }
}
