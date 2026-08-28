<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Stamped when the user's name has been verified against Oracle;
            // NULL means the nightly sweep still needs to process this user.
            $table->timestamp('oracle_synced_at')->nullable()->after('reg_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('oracle_synced_at');
        });
    }
};
