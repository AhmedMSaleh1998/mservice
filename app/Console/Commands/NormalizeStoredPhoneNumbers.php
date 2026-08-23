<?php

namespace App\Console\Commands;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites phone numbers that were stored before PhoneNumberNormalizer learned
 * to peel stacked country codes and fold Arabic-Indic digits. Numbers already
 * in canonical form are left alone, and anything that does not resolve to a
 * recognised Egyptian mobile is reported rather than rewritten.
 */
class NormalizeStoredPhoneNumbers extends Command
{
    protected $signature = 'phones:normalize
                            {--apply : Write the changes; without this the command only reports}
                            {--table=* : Limit the run to these tables}';

    protected $description = 'Normalize stored phone numbers to the canonical E.164 form.';

    /**
     * @var array<string, string> table => phone column
     */
    private const TARGETS = [
        'users' => 'phone',
        'otp' => 'phone',
        'certificate_requests' => 'phone',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $only = (array) $this->option('table');

        $targets = $only === []
            ? self::TARGETS
            : array_intersect_key(self::TARGETS, array_flip($only));

        if ($targets === []) {
            $this->error('No known table selected. Available: ' . implode(', ', array_keys(self::TARGETS)));

            return self::FAILURE;
        }

        $totalChanged = 0;
        $totalSkipped = 0;

        foreach ($targets as $table => $column) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->warn(sprintf('Skipping %s — table does not exist.', $table));
                continue;
            }

            [$changed, $skipped] = $this->processTable($table, $column, $apply);

            $totalChanged += $changed;
            $totalSkipped += $skipped;
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d row(s) to rewrite, %d unrecognised row(s) left untouched.',
            $apply ? 'Applied' : 'Dry run',
            $totalChanged,
            $totalSkipped,
        ));

        if (! $apply && $totalChanged > 0) {
            $this->comment('Re-run with --apply to write these changes.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} changed, skipped
     */
    private function processTable(string $table, string $column, bool $apply): array
    {
        $changed = 0;
        $skipped = 0;
        $rows = [];

        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($column, &$changed, &$skipped, &$rows): void {
                foreach ($chunk as $row) {
                    $current = (string) $row->{$column};
                    $normalized = PhoneNumberNormalizer::normalize($current);

                    if ($normalized === $current) {
                        continue;
                    }

                    // Only rewrite what we can positively identify; anything else
                    // (landlines, foreign numbers, junk) is reported instead.
                    if (! PhoneNumberNormalizer::isValidMobile($current)) {
                        $skipped++;
                        $rows[] = [$row->id, $current, '—', 'unrecognised'];
                        continue;
                    }

                    $changed++;
                    $rows[] = [$row->id, $current, $normalized, 'rewrite'];
                }
            });

        $this->newLine();
        $this->info(sprintf('%s.%s', $table, $column));

        if ($rows === []) {
            $this->line('  Nothing to change.');

            return [0, 0];
        }

        $this->table(['id', 'stored', 'normalized', 'action'], $rows);

        if ($apply) {
            foreach ($rows as [$id, $current, $normalized, $action]) {
                if ($action !== 'rewrite') {
                    continue;
                }

                DB::table($table)->where('id', $id)->update([$column => $normalized]);
            }

            $this->info(sprintf('  Wrote %d row(s).', $changed));
        }

        return [$changed, $skipped];
    }
}
