<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [];

        if (Schema::hasColumn('medical_guides', 'type')) {
            $columns[] = 'type';
        }

        if (Schema::hasColumn('medical_guides', 'lat')) {
            $columns[] = 'lat';
        }

        if (Schema::hasColumn('medical_guides', 'lng')) {
            $columns[] = 'lng';
        }

        if ($columns !== []) {
            Schema::table('medical_guides', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('medical_guides', function (Blueprint $table) {
            if (!Schema::hasColumn('medical_guides', 'type')) {
                $table->string('type')->default('doctor')->after('description');
            }

            if (!Schema::hasColumn('medical_guides', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('type');
            }

            if (!Schema::hasColumn('medical_guides', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
        });
    }
};
