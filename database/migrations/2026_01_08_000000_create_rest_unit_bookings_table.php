<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rest_unit_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rest_unit_id')->constrained('rest_units')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable(); // Nullable for offline bookings or guest checkout if needed
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('active'); // active, cancelled, completed
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rest_unit_bookings');
    }
};
