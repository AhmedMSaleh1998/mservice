<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_unit_bookings', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('rest_unit_bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
