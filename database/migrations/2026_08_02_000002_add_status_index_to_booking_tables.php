<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose `status` column is polled every minute by the release-expired scheduler.
     */
    private array $tables = [
        'ad_requests',
        'course_bookings',
        'rest_unit_bookings',
        'travel_bookings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'status') || $this->hasStatusIndex($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->index('status');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! $this->hasStatusIndex($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['status']);
            });
        }
    }

    private function hasStatusIndex(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (in_array('status', $index['columns'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
};
