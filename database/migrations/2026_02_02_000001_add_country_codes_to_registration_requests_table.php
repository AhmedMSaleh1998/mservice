<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->string('residence_mobile_1_country_code', 8)->nullable()->after('residence_mobile_1');
            $table->string('residence_mobile_2_country_code', 8)->nullable()->after('residence_mobile_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn([
                'residence_mobile_1_country_code',
                'residence_mobile_2_country_code',
            ]);
        });
    }
};
