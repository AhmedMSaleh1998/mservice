<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms and beds become individually-tracked units again so the admin can send a
 * specific room/bed to maintenance and bookings can target a concrete unit
 * (auto-assigned by the API, hand-picked from the dashboard).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('rest_unit_rooms')) {
            Schema::create('rest_unit_rooms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rest_unit_id')->constrained('rest_units')->cascadeOnDelete();
                $table->foreignId('room_type_id')->constrained('room_types');
                $table->string('name');
                $table->decimal('price', 10, 2)->default(0);
                $table->string('status')->default('in_service'); // in_service / maintenance
                $table->text('maintenance_note')->nullable();
                $table->timestamp('maintenance_started_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rest_unit_beds')) {
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
        }

        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_unit_bookings', 'rest_unit_room_id')) {
                $table->foreignId('rest_unit_room_id')->nullable()->after('rest_unit_id')
                    ->constrained('rest_unit_rooms')->nullOnDelete();
            }
            if (! Schema::hasColumn('rest_unit_bookings', 'rest_unit_bed_id')) {
                $table->foreignId('rest_unit_bed_id')->nullable()->after('rest_unit_room_id')
                    ->constrained('rest_unit_beds')->nullOnDelete();
            }
        });

        // Fold count-based room groups into individual rooms.
        if (Schema::hasTable('rest_unit_room_groups')) {
            $groups = DB::table('rest_unit_room_groups')->whereNull('deleted_at')->get();

            foreach ($groups as $group) {
                $firstRoomId = null;
                $total = (int) $group->total_count;
                $maintenance = (int) $group->maintenance_count;

                for ($i = 1; $i <= $total; $i++) {
                    $id = DB::table('rest_unit_rooms')->insertGetId([
                        'rest_unit_id' => $group->rest_unit_id,
                        'room_type_id' => $group->room_type_id,
                        'name' => 'Room '.$i,
                        'price' => $group->price,
                        'status' => $i <= $maintenance ? 'maintenance' : 'in_service',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $firstRoomId ??= $id;
                }

                if ($firstRoomId && Schema::hasColumn('rest_unit_bookings', 'rest_unit_room_group_id')) {
                    DB::table('rest_unit_bookings')
                        ->where('rest_unit_room_group_id', $group->id)
                        ->update(['rest_unit_room_id' => $firstRoomId]);
                }
            }
        }

        // Fold bed counts into individual beds.
        if (Schema::hasColumn('rest_units', 'beds_total')) {
            $units = DB::table('rest_units')->where('type', 'beds')->get();

            foreach ($units as $unit) {
                $total = (int) $unit->beds_total;
                $maintenance = (int) $unit->beds_maintenance;

                for ($i = 1; $i <= $total; $i++) {
                    DB::table('rest_unit_beds')->insert([
                        'rest_unit_id' => $unit->id,
                        'label' => 'Bed '.$i,
                        'status' => $i <= $maintenance ? 'maintenance' : 'in_service',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Drop the old count-based structures.
        if (Schema::hasColumn('rest_unit_bookings', 'rest_unit_room_group_id')) {
            Schema::table('rest_unit_bookings', function (Blueprint $table): void {
                $table->dropForeign(['rest_unit_room_group_id']);
                $table->dropColumn('rest_unit_room_group_id');
            });
        }

        Schema::dropIfExists('rest_unit_room_groups');

        if (Schema::hasColumn('rest_units', 'beds_total')) {
            Schema::table('rest_units', function (Blueprint $table): void {
                $table->dropColumn(['beds_total', 'beds_maintenance']);
            });
        }
    }

    public function down(): void
    {
        // Structural, non-destructive reconcile forward only.
    }
};
