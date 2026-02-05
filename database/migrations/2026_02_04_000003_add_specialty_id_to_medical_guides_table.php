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
        Schema::table('medical_guides', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('description')
                ->constrained('medical_specialties')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
