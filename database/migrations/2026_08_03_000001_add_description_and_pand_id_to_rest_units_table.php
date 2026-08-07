<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rest_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('rest_units', 'description')) {
                // Translatable description shown for the rest unit.
                $table->json('description')->nullable()->after('address');
            }

            if (! Schema::hasColumn('rest_units', 'pand_id')) {
                // Oracle "pand" (item) number sent when syncing a rest unit payment.
                $table->unsignedBigInteger('pand_id')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rest_units', function (Blueprint $table): void {
            foreach (['description', 'pand_id'] as $column) {
                if (Schema::hasColumn('rest_units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
