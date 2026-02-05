<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('medical_guides', 'province_id')) {
            Schema::table('medical_guides', function (Blueprint $table) {
                $table->foreignId('province_id')
                    ->nullable()
                    ->after('specialty_id')
                    ->constrained('provinces')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('medical_guides', 'address')) {
            Schema::table('medical_guides', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('medical_guides', 'province_id')) {
            Schema::table('medical_guides', function (Blueprint $table) {
                $table->dropConstrainedForeignId('province_id');
            });
        }

        if (!Schema::hasColumn('medical_guides', 'address')) {
            Schema::table('medical_guides', function (Blueprint $table) {
                $table->json('address')->after('description');
            });
        }
    }
};
