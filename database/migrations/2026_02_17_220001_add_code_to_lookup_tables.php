<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            if (! Schema::hasColumn('provinces', 'code')) {
                $table->unsignedInteger('code')->nullable()->after('id');
            }
        });

        Schema::table('nationalities', function (Blueprint $table): void {
            if (! Schema::hasColumn('nationalities', 'code')) {
                $table->unsignedInteger('code')->nullable()->after('id');
            }
        });

        Schema::table('medical_universities', function (Blueprint $table): void {
            if (! Schema::hasColumn('medical_universities', 'code')) {
                $table->unsignedInteger('code')->nullable()->after('id');
            }
        });

        Schema::table('grades', function (Blueprint $table): void {
            if (! Schema::hasColumn('grades', 'code')) {
                $table->unsignedInteger('code')->nullable()->after('id');
            }
        });

        Schema::table('provinces', function (Blueprint $table): void {
            $table->unique('code', 'provinces_code_unique');
        });

        Schema::table('nationalities', function (Blueprint $table): void {
            $table->unique('code', 'nationalities_code_unique');
        });

        Schema::table('medical_universities', function (Blueprint $table): void {
            $table->unique('code', 'medical_universities_code_unique');
        });

        Schema::table('grades', function (Blueprint $table): void {
            $table->unique('code', 'grades_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->dropUnique('provinces_code_unique');
            $table->dropColumn('code');
        });

        Schema::table('nationalities', function (Blueprint $table): void {
            $table->dropUnique('nationalities_code_unique');
            $table->dropColumn('code');
        });

        Schema::table('medical_universities', function (Blueprint $table): void {
            $table->dropUnique('medical_universities_code_unique');
            $table->dropColumn('code');
        });

        Schema::table('grades', function (Blueprint $table): void {
            $table->dropUnique('grades_code_unique');
            $table->dropColumn('code');
        });
    }
};
