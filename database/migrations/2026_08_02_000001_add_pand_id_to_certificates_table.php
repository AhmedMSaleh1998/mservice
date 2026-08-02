<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certificates', 'pand_id')) {
            Schema::table('certificates', function (Blueprint $table): void {
                // Oracle certificate number (P_PAND_ID) sent when syncing a certificate payment.
                $table->unsignedBigInteger('pand_id')->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('certificates', 'pand_id')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->dropColumn('pand_id');
            });
        }
    }
};
