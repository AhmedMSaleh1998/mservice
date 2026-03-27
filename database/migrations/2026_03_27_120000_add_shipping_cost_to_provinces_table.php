<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->decimal('shipping_cost', 10, 2)->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->dropColumn('shipping_cost');
        });
    }
};
