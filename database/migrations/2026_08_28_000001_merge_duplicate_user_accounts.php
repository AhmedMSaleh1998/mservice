<?php

use App\Support\DuplicateUserAccountMerger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One-time cleanup: merges every set of accounts sharing a registration
 * number into a single surviving account (active > most recent session >
 * newest), migrating all related records before deleting the rest.
 * Every deleted account is logged with its name, phone, and national ID.
 * A unique index then makes a duplicate registration number impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stats = app(DuplicateUserAccountMerger::class)->merge();

        Log::info('Duplicate user account merge migration finished.', $stats);

        // Accounts without a registration number must stay allowed (multiple
        // NULLs pass a unique index; multiple empty strings would not).
        DB::table('users')->where('reg_number', '')->update(['reg_number' => null]);

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('reg_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['reg_number']);
        });

        // The merge itself is irreversible: deleted accounts cannot be reconstructed.
    }
};
