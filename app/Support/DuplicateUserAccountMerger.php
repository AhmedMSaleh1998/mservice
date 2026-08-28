<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Users\Models\User;

/**
 * Collapses duplicate accounts that share a registration number down to one.
 *
 * Survivor selection, in order:
 *   1. A single active account always survives, whatever its age.
 *   2. Among several active accounts, the one with the most recent login
 *      session survives; with no sessions anywhere, the newest active one.
 *   3. With no active account at all, the newest account survives.
 *
 * Every other account has its records migrated to the survivor first
 * (orders, bookings, requests, tickets, addresses, ...) and is then deleted
 * along with its login tokens, role assignments, and media rows.
 */
class DuplicateUserAccountMerger
{
    private const MIGRATE_TABLES = [
        'orders',
        'rest_unit_bookings',
        'course_bookings',
        'travel_bookings',
        'certificate_requests',
        'support_tickets',
        'ad_requests',
        'membership_requests',
        'user_addresses',
        'audit_logs',
        'blogs',
    ];

    /**
     * @return array{groups: int, merged: int, accounts_deleted: int, rows_migrated: int}
     */
    public function merge(bool $apply = true): array
    {
        $migrateTables = array_values(array_filter(
            self::MIGRATE_TABLES,
            fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'user_id'),
        ));

        $duplicateRegs = DB::table('users')
            ->select('reg_number')
            ->whereNotNull('reg_number')
            ->where('reg_number', '!=', '')
            ->groupBy('reg_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('reg_number');

        $stats = ['groups' => 0, 'merged' => 0, 'accounts_deleted' => 0, 'rows_migrated' => 0];

        foreach ($duplicateRegs as $regNumber) {
            $stats['groups']++;

            $accounts = DB::table('users')
                ->where('reg_number', $regNumber)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $survivor = $this->pickSurvivor($accounts);
            $losers = $accounts->reject(fn (object $account): bool => $account->id === $survivor->id);

            if (! $apply) {
                $stats['merged']++;
                $stats['accounts_deleted'] += $losers->count();

                continue;
            }

            $migrated = DB::transaction(function () use ($survivor, $losers, $migrateTables): int {
                $migrated = 0;

                foreach ($losers as $loser) {
                    $migrated += $this->absorbAccount($survivor, $loser, $migrateTables);
                }

                return $migrated;
            });

            $stats['merged']++;
            $stats['accounts_deleted'] += $losers->count();
            $stats['rows_migrated'] += $migrated;
        }

        return $stats;
    }

    /**
     * @param Collection<int, object> $accounts ordered oldest -> newest
     */
    private function pickSurvivor(Collection $accounts): object
    {
        $actives = $accounts->filter(fn (object $account): bool => (bool) $account->active)->values();

        if ($actives->count() === 1) {
            return $actives->first();
        }

        if ($actives->count() > 1) {
            $bySession = $actives
                ->map(fn (object $account): array => [$account, $this->latestSessionActivity((int) $account->id)])
                ->filter(fn (array $pair): bool => $pair[1] !== null)
                ->sortBy(fn (array $pair): string => $pair[1]);

            if ($bySession->isNotEmpty()) {
                return $bySession->last()[0];
            }

            return $actives->last();
        }

        return $accounts->last();
    }

    private function latestSessionActivity(int $userId): ?string
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User())->getMorphClass())
            ->where('tokenable_id', $userId)
            ->selectRaw('MAX(COALESCE(last_used_at, created_at)) latest')
            ->value('latest');
    }

    /**
     * @param list<string> $migrateTables
     */
    private function absorbAccount(object $survivor, object $loser, array $migrateTables): int
    {
        $migrated = 0;

        foreach ($migrateTables as $table) {
            $migrated += DB::table($table)
                ->where('user_id', $loser->id)
                ->update(['user_id' => $survivor->id]);
        }

        // Kill anything that would let the deleted account act again.
        $userMorph = (new User())->getMorphClass();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', $userMorph)
            ->where('tokenable_id', $loser->id)
            ->delete();

        foreach (['model_has_roles', 'model_has_permissions'] as $pivotTable) {
            if (Schema::hasTable($pivotTable)) {
                DB::table($pivotTable)
                    ->where('model_type', $userMorph)
                    ->where('model_id', $loser->id)
                    ->delete();
            }
        }

        if (Schema::hasTable('media')) {
            DB::table('media')
                ->where('model_type', $userMorph)
                ->where('model_id', $loser->id)
                ->delete();
        }

        DB::table('users')->where('id', $loser->id)->delete();

        Log::info('Duplicate user account merged.', [
            'reg_number' => $survivor->reg_number,
            'survivor_id' => $survivor->id,
            'deleted_id' => $loser->id,
            'deleted_name' => $loser->name,
            'deleted_phone' => $loser->phone,
            'deleted_national_id' => $loser->national_id,
            'rows_migrated' => $migrated,
        ]);

        return $migrated;
    }
}
