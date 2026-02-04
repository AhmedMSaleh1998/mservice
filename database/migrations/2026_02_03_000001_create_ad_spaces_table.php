<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_spaces', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->unsignedInteger('max_characters')->nullable();
            $table->unsignedInteger('min_duration_months')->default(1);
            $table->decimal('price_per_month', 10, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spaces');
    }
};
