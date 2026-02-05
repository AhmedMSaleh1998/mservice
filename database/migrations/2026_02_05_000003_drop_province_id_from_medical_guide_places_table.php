<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('medical_guide_places', 'province_id')) {
            Schema::table('medical_guide_places', function (Blueprint $table) {
                $table->dropConstrainedForeignId('province_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('medical_guide_places', 'province_id')) {
            Schema::table('medical_guide_places', function (Blueprint $table) {
                $table->foreignId('province_id')
                    ->nullable()
                    ->after('address')
                    ->constrained('provinces');
            });
        }
    }
};
