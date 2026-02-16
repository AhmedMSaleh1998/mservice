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
        if (Schema::hasColumn('roles', 'translated_name')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->json('translated_name')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('roles', 'translated_name')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('translated_name');
        });
    }
};
