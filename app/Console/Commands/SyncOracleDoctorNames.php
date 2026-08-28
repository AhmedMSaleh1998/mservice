<?php

namespace App\Console\Commands;

use App\Services\OracleNameSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Users\Models\User;

/**
 * Reconciles user names with the syndicate's Oracle records. Each user is
 * first verified against Oracle by registration number AND national ID
 * (CHK_DOCTOR_EXIST); only a verified identity gets its name refreshed from
 * GET_DOCTOR_DATA_REGNO. Users whose identity does not verify are reported,
 * never rewritten. Oracle records without a name are reported and left alone.
 *
 * A successful pass stamps users.oracle_synced_at, and the default run skips
 * stamped users — so the nightly sweep only retries unresolved cases. Pass
 * --all to re-check everyone (e.g. after the syndicate bulk-fixes records).
 */
class SyncOracleDoctorNames extends Command
{
    protected $signature = 'users:sync-oracle-names
                            {--apply : Write the changes; without this the command only reports}
                            {--all : Re-check users already marked as synced, not just pending ones}
                            {--user=* : Limit the run to these user IDs}
                            {--limit=0 : Stop after processing this many users (0 = all)}';

    protected $description = 'Verify users against Oracle (registration number + national ID) and update their names from syndicate records.';

    private const MAX_CONSECUTIVE_FAILURES = 5;

    public function __construct(
        private readonly OracleNameSyncService $oracleNameSyncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $all = (bool) $this->option('all');
        $userIds = array_filter(array_map('intval', (array) $this->option('user')));
        $limit = max(0, (int) $this->option('limit'));

        if (! $apply) {
            $this->warn('Report-only run. Pass --apply to write the changes.');
        }

        $query = User::query()
            ->whereNotNull('reg_number')->where('reg_number', '!=', '')
            ->whereNotNull('national_id')->where('national_id', '!=', '')
            ->when(! $all && $userIds === [], fn ($q) => $q->whereNull('oracle_synced_at'))
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

        Log::info('Oracle name sync finished.', [...$stats, 'apply' => $apply, 'all' => $all, 'aborted' => $aborted]);

        return $aborted ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string, int> $stats
     */
    private function syncUser(User $user, bool $apply, array &$stats): void
    {
        $oldName = $user->name;
        $result = $this->oracleNameSyncService->syncUser($user, $apply);
        $outcome = $result['outcome'];

        if ($outcome === OracleNameSyncService::OUTCOME_IDENTITY_MISMATCH) {
            $stats['identity_mismatch']++;
            $this->warn(sprintf(
                '#%d %s: registration number + national ID do not match any Oracle doctor — left untouched.',
                $user->id,
                $user->reg_number,
            ));

            return;
        }

        if ($outcome === OracleNameSyncService::OUTCOME_NO_ORACLE_NAME) {
            $stats['no_oracle_name']++;
            $this->line(sprintf(
                '#%d %s: Oracle data lookup has no record or no name for this number — left untouched.',
                $user->id,
                $user->reg_number,
            ));

            return;
        }

        if ($outcome === OracleNameSyncService::OUTCOME_ALREADY_MATCHING) {
            $stats['already_matching']++;

            return;
        }

        if ($outcome === OracleNameSyncService::OUTCOME_UPDATED) {
            $stats['updated']++;
            $this->info(sprintf(
                '#%d %s: "%s" -> "%s"%s',
                $user->id,
                $user->reg_number,
                $oldName,
                $result['oracle_name'],
                $apply ? '' : ' [REPORT ONLY — nothing saved, run with --apply]',
            ));
        }
    }
}
