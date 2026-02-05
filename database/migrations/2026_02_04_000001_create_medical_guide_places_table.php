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
        Schema::create('medical_guide_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_guide_id')
                ->constrained('medical_guides')
                ->cascadeOnDelete();
            $table->json('name');
            $table->json('address')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('phones')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_guide_places');
    }
};
