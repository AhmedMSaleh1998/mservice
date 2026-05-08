<?php

namespace App\Console\Commands;

use App\Services\Oracle\OracleMedicalGuideSyncService;
use Illuminate\Console\Command;

class SyncOracleMedicalGuides extends Command
{
    protected $signature = 'medical-guides:sync-oracle {--limit= : Limit rows fetched from Oracle for testing}';

    protected $description = 'Sync medical guide data from the Oracle syndicate view.';

    public function __construct(
        private readonly OracleMedicalGuideSyncService $syncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('services.oracle.medical_guide_sync_enabled', true)) {
            $this->warn('Oracle medical guide sync is disabled.');

            return self::SUCCESS;
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $stats = $this->syncService->sync($limit);

        $this->info(sprintf(
            'Medical guides sync completed. fetched=%d created=%d updated=%d unchanged=%d duplicates_skipped=%d invalid_skipped=%d',
            $stats['fetched'],
            $stats['created'],
            $stats['updated'],
            $stats['unchanged'],
            $stats['duplicates_skipped'],
            $stats['invalid_skipped'],
        ));

        return self::SUCCESS;
    }
}
