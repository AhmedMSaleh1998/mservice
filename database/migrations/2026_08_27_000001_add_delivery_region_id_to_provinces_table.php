<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            // Fixed region codes mirroring the Oracle delivery regions table
            // (see Province::DELIVERY_REGIONS). Nullable so existing provinces
            // keep working; the Filament form makes it required going forward.
            $table->unsignedInteger('delivery_region_id')->nullable()->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->dropColumn('delivery_region_id');
        });
    }
};
