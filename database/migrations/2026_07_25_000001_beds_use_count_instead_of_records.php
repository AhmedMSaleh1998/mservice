<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beds are interchangeable, so a beds-type rest unit no longer keeps one record per bed.
 * It stores a total count and a maintenance count on the unit itself (same model as room groups).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_units', 'beds_total')) {
                $table->unsignedInteger('beds_total')->default(0)->after('price');
            }
            if (! Schema::hasColumn('rest_units', 'beds_maintenance')) {
                $table->unsignedInteger('beds_maintenance')->default(0)->after('beds_total');
            }
        });

        // Fold any existing individual beds into the new counts.
        if (Schema::hasTable('rest_unit_beds')) {
            $counts = DB::table('rest_unit_beds')
                ->whereNull('deleted_at')
                ->selectRaw("rest_unit_id,
                    SUM(CASE WHEN status = 'in_service' THEN 1 ELSE 0 END) AS in_service,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance")
                ->groupBy('rest_unit_id')
                ->get();

            foreach ($counts as $row) {
                DB::table('rest_units')->where('id', $row->rest_unit_id)->update([
                    'beds_total' => (int) $row->in_service + (int) $row->maintenance,
                    'beds_maintenance' => (int) $row->maintenance,
                ]);
            }
        }

        // Bookings no longer point at a concrete bed.
        if (Schema::hasColumn('rest_unit_bookings', 'rest_unit_bed_id')) {
            Schema::table('rest_unit_bookings', function (Blueprint $table): void {
                $table->dropForeign(['rest_unit_bed_id']);
                $table->dropColumn('rest_unit_bed_id');
            });
        }

        Schema::dropIfExists('rest_unit_beds');
    }

    public function down(): void
    {
        Schema::create('rest_unit_beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rest_unit_id')->constrained('rest_units')->cascadeOnDelete();
            $table->string('label');
            $table->string('status')->default('in_service');
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_started_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_unit_bookings', 'rest_unit_bed_id')) {
                $table->foreignId('rest_unit_bed_id')->nullable()->after('rest_unit_id')
                    ->constrained('rest_unit_beds')->nullOnDelete();
            }
        });

        Schema::table('rest_units', function (Blueprint $table): void {
            $table->dropColumn(['beds_total', 'beds_maintenance']);
        });
    }
};
