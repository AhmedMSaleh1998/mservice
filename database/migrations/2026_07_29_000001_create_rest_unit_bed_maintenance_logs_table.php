<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('rest_unit_bed_maintenance_logs')) {
            Schema::create('rest_unit_bed_maintenance_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rest_unit_bed_id')->constrained('rest_unit_beds')->cascadeOnDelete();
                $table->string('action'); // to_maintenance / returned_to_service
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_unit_bed_maintenance_logs');
    }
};
