<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_space_id')->constrained('ad_spaces')->cascadeOnDelete();
            $table->unsignedInteger('duration_months');
            $table->decimal('price_per_month', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->text('ad_text')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_requests');
    }
};
