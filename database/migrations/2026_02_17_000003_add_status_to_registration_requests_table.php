<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->string('status', 40)
                ->default('pending_review')
                ->after('active');
        });

        DB::table('registration_requests')
            ->where('active', true)
            ->update(['status' => 'approved']);

        DB::table('registration_requests')
            ->where('active', false)
            ->update(['status' => 'pending_review']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
