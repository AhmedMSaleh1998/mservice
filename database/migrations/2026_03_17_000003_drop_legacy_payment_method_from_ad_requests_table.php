<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_requests', function (Blueprint $table) {
            if (Schema::hasColumn('ad_requests', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_requests', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status');
            }
        });
    }
};
