<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'pand_id')) {
            Schema::table('courses', function (Blueprint $table): void {
                // Oracle course number sent when syncing a course payment.
                $table->unsignedBigInteger('pand_id')->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'pand_id')) {
            Schema::table('courses', function (Blueprint $table): void {
                $table->dropColumn('pand_id');
            });
        }
    }
};
