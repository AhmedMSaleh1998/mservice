<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_guides', function (Blueprint $table): void {
            if (! Schema::hasColumn('medical_guides', 'reg_number')) {
                $table->string('reg_number', 100)->nullable()->after('id');
            }

            if (! Schema::hasColumn('medical_guides', 'oracle_payload')) {
                $table->json('oracle_payload')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('medical_guides', 'oracle_synced_at')) {
                $table->timestamp('oracle_synced_at')->nullable()->after('oracle_payload');
            }

            if (! Schema::hasColumn('medical_guides', 'oracle_last_changed_at')) {
                $table->timestamp('oracle_last_changed_at')->nullable()->after('oracle_synced_at');
            }
        });

        Schema::table('medical_guides', function (Blueprint $table): void {
            $table->unique('reg_number', 'medical_guides_reg_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('medical_guides', function (Blueprint $table): void {
            $table->dropUnique('medical_guides_reg_number_unique');
        });

        Schema::table('medical_guides', function (Blueprint $table): void {
            $table->dropColumn([
                'reg_number',
                'oracle_payload',
                'oracle_synced_at',
                'oracle_last_changed_at',
            ]);
        });
    }
};
