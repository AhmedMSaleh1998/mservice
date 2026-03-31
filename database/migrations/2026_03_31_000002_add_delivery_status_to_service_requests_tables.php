<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('certificate_requests', 'delivery_status')) {
                $table->string('delivery_status')->nullable()->after('status');
            }
        });

        Schema::table('membership_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_requests', 'delivery_status')) {
                $table->string('delivery_status')->nullable()->after('status');
            }
        });

        DB::table('certificate_requests')
            ->where('status', 'pending')
            ->update(['status' => 'pending_payment']);

        DB::table('certificate_requests')
            ->where('delivery_method', 'delivery')
            ->whereNull('delivery_status')
            ->update(['delivery_status' => 'pending']);

        DB::table('membership_requests')
            ->whereNull('delivery_status')
            ->update(['delivery_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('certificate_requests', 'delivery_status')) {
                $table->dropColumn('delivery_status');
            }
        });

        Schema::table('membership_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('membership_requests', 'delivery_status')) {
                $table->dropColumn('delivery_status');
            }
        });
    }
};
