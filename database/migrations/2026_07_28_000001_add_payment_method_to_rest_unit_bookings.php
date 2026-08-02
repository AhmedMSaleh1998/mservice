<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_unit_bookings', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('total_price'); // cash / bank_transfer / fawry
            }
        });
    }

    public function down(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('rest_unit_bookings', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
