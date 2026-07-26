<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles databases where the earlier restructure migration ran against its
 * previous (rooms/beds hierarchy) version onto the final 3-type model
 * (beds / rooms via room groups / whole unit). Fully guarded so it is a no-op
 * on a fresh database where the final schema is already in place.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) rest_units: whole-unit price + maintenance columns.
        Schema::table('rest_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_units', 'price')) {
                $table->decimal('price', 10, 2)->default(0)->after('type');
            }
            if (! Schema::hasColumn('rest_units', 'status')) {
                $table->string('status')->default('in_service')->after('price');
            }
            if (! Schema::hasColumn('rest_units', 'maintenance_note')) {
                $table->text('maintenance_note')->nullable()->after('status');
            }
        });

        // 2) Global room types lookup.
        if (! Schema::hasTable('room_types')) {
            Schema::create('room_types', function (Blueprint $table): void {
                $table->id();
                $table->json('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 3) Only the drifted (old-hierarchy) databases still have rest_unit_rooms.
        if (Schema::hasTable('rest_unit_rooms')) {
            // Detach booking foreign keys that reference the tables we are about to rebuild.
            Schema::table('rest_unit_bookings', function (Blueprint $table): void {
                if (Schema::hasColumn('rest_unit_bookings', 'rest_unit_bed_id')) {
                    $table->dropForeign(['rest_unit_bed_id']);
                }
            });

            Schema::table('rest_unit_bookings', function (Blueprint $table): void {
                if (Schema::hasColumn('rest_unit_bookings', 'rest_unit_room_id')) {
                    $table->dropConstrainedForeignId('rest_unit_room_id');
                }
            });

            Schema::dropIfExists('rest_unit_beds');
            Schema::dropIfExists('rest_unit_rooms');

            // Rebuild beds directly under a rest unit.
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

            if (! Schema::hasTable('rest_unit_room_groups')) {
                Schema::create('rest_unit_room_groups', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('rest_unit_id')->constrained('rest_units')->cascadeOnDelete();
                    $table->foreignId('room_type_id')->constrained('room_types');
                    $table->unsignedInteger('total_count')->default(0);
                    $table->unsignedInteger('maintenance_count')->default(0);
                    $table->decimal('price', 10, 2)->default(0);
                    $table->softDeletes();
                    $table->timestamps();
                });
            }

            Schema::table('rest_unit_bookings', function (Blueprint $table): void {
                if (! Schema::hasColumn('rest_unit_bookings', 'rest_unit_room_group_id')) {
                    $table->foreignId('rest_unit_room_group_id')->nullable()->after('rest_unit_bed_id')
                        ->constrained('rest_unit_room_groups')->nullOnDelete();
                }
                // Re-attach the bed foreign key to the rebuilt table.
                $table->foreign('rest_unit_bed_id')->references('id')->on('rest_unit_beds')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive reconcile; nothing to reverse safely.
    }
};
