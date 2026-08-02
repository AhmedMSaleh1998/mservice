<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_unit_bookings', 'payment_reference')) {
                // Transfer / transaction number for offline (martyr-family) payments.
                $table->string('payment_reference')->nullable()->after('beneficiary_reg_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('rest_unit_bookings', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });
    }
};
