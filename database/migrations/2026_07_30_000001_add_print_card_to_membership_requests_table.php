<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_requests', 'print_card')) {
                // Existing rows already had a printed card, so default true to preserve their meaning.
                $table->boolean('print_card')->default(true)->after('registration_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('membership_requests', 'print_card')) {
                $table->dropColumn('print_card');
            }
        });
    }
};
