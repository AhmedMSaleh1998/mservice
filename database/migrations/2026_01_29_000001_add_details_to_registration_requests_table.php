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
            $table->string('full_name_ar')->nullable();
            $table->string('full_name_en')->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->string('governorate', 100)->nullable();
            $table->string('issued_from', 100)->nullable();
            $table->string('birth_governorate', 100)->nullable();
            $table->date('birth_date')->nullable();

            $table->string('residence_governorate', 100)->nullable();
            $table->string('residence_center', 100)->nullable();
            $table->string('residence_street')->nullable();
            $table->string('residence_house_number', 50)->nullable();
            $table->string('residence_phone', 25)->nullable();
            $table->string('residence_mobile_1', 25)->nullable();
            $table->string('residence_mobile_2', 25)->nullable();
            $table->string('email')->nullable();

            $table->string('university')->nullable();
            $table->string('faculty')->nullable();
            $table->string('graduation_year', 10)->nullable();
            $table->string('graduation_month', 20)->nullable();
            $table->string('grade', 100)->nullable();
            $table->string('first_foreign_language', 50)->nullable();
            $table->string('second_foreign_language', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn([
                'full_name_ar',
                'full_name_en',
                'gender',
                'nationality',
                'religion',
                'governorate',
                'issued_from',
                'birth_governorate',
                'birth_date',
                'residence_governorate',
                'residence_center',
                'residence_street',
                'residence_house_number',
                'residence_phone',
                'residence_mobile_1',
                'residence_mobile_2',
                'email',
                'university',
                'faculty',
                'graduation_year',
                'graduation_month',
                'grade',
                'first_foreign_language',
                'second_foreign_language',
            ]);
        });
    }
};
