<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_units', function (Blueprint $table): void {
            $table->unsignedInteger('triple_rooms')->default(0)->after('double_rooms');
            $table->decimal('triple_room_price', 10, 2)->default(0)->after('double_room_price');
        });

        DB::table('rest_units')->update([
            'triple_rooms' => DB::raw('single_bed'),
            'triple_room_price' => DB::raw('single_bed_price'),
        ]);

        Schema::table('rest_units', function (Blueprint $table): void {
            $table->dropColumn(['single_bed', 'single_bed_price']);
        });

        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            $table->dropColumn('paid_at');
        });

        Schema::table('rest_units', function (Blueprint $table): void {
            $table->unsignedInteger('single_bed')->default(0)->after('double_rooms');
            $table->decimal('single_bed_price', 10, 2)->default(0)->after('double_room_price');
        });

        DB::table('rest_units')->update([
            'single_bed' => DB::raw('triple_rooms'),
            'single_bed_price' => DB::raw('triple_room_price'),
        ]);

        Schema::table('rest_units', function (Blueprint $table): void {
            $table->dropColumn(['triple_rooms', 'triple_room_price']);
        });
    }
};
