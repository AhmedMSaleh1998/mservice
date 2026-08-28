<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('delivery_region_id');
        });
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->dropColumn('active');
        });
    }
};
