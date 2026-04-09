<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travels', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('location')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->json('meeting_point_title')->nullable();
            $table->json('meeting_point_description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('travel_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('travel_id');
            $table->string('code', 50);
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();

            $table->unique(['travel_id', 'code']);
        });

        Schema::create('travel_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('travel_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('pending_payment');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->unsignedInteger('participants_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('travel_booking_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('travel_booking_id');
            $table->unsignedBigInteger('travel_category_id')->nullable();
            $table->string('category_code', 50)->nullable();
            $table->string('category_name');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_booking_items');
        Schema::dropIfExists('travel_bookings');
        Schema::dropIfExists('travel_categories');
        Schema::dropIfExists('travels');
    }
};
