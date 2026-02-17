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
            $table->string('license_number', 100)->nullable()->after('second_foreign_language');
            $table->date('license_date')->nullable()->after('license_number');
            $table->string('license_image')->nullable()->after('license_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn([
                'license_number',
                'license_date',
                'license_image',
            ]);
        });
    }
};
