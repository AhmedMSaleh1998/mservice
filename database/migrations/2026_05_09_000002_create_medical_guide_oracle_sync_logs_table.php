<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_guide_oracle_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medical_guide_id')
                ->constrained('medical_guides')
                ->cascadeOnDelete();
            $table->string('reg_number', 100);
            $table->string('action', 30);
            $table->json('changed_fields')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->index(['medical_guide_id', 'synced_at'], 'medical_guide_oracle_logs_guide_synced_idx');
            $table->index('reg_number', 'medical_guide_oracle_logs_reg_number_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_guide_oracle_sync_logs');
    }
};
