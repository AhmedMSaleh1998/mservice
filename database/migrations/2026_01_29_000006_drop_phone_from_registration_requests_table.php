<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('registration_requests', 'phone')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('registration_requests', 'phone')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->string('phone')->unique()->after('id');
            });
        }
    }
};
