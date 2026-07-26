<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared SQLite schema for the rest-unit domain (individual rooms/beds) used across feature tests.
 */
class RestUnitTestSchema
{
    public static function create(string $connection = 'sqlite'): void
    {
        Schema::connection($connection)->create('rest_units', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('address')->nullable();
            $table->unsignedBigInteger('province_id');
            $table->string('type')->default('beds');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('in_service');
            $table->text('maintenance_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('rest_unit_rooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rest_unit_id');
            $table->unsignedBigInteger('room_type_id');
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('in_service');
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_started_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('rest_unit_beds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rest_unit_id');
            $table->string('label');
            $table->string('status')->default('in_service');
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_started_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('rest_unit_bed_maintenance_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rest_unit_bed_id');
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('rest_unit_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rest_unit_id');
            $table->unsignedBigInteger('rest_unit_room_id')->nullable();
            $table->unsignedBigInteger('rest_unit_bed_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('beneficiary_type')->default('member');
            $table->string('beneficiary_name')->nullable();
            $table->string('beneficiary_card_number')->nullable();
            $table->string('beneficiary_reg_number')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('unit_type')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending_payment');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
