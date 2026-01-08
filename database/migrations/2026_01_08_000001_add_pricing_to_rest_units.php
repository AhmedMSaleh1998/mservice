<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_units', function (Blueprint $table) {
            $table->decimal('single_room_price', 10, 2)->default(0)->after('single_rooms');
            $table->decimal('double_room_price', 10, 2)->default(0)->after('double_rooms');
            $table->decimal('single_bed_price', 10, 2)->default(0)->after('single_bed');
        });

        Schema::table('rest_unit_bookings', function (Blueprint $table) {
            $table->string('unit_type')->nullable()->after('rest_unit_id'); // e.g. single_rooms, double_rooms
            $table->decimal('total_price', 10, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('rest_units', function (Blueprint $table) {
            $table->dropColumn(['single_room_price', 'double_room_price', 'single_bed_price']);
        });

        Schema::table('rest_unit_bookings', function (Blueprint $table) {
            $table->dropColumn(['unit_type', 'total_price']);
        });
    }
};
