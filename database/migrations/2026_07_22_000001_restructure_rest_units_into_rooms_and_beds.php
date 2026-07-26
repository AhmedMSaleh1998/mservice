<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Rest unit gains a kind (beds / rooms / whole_unit) and whole-unit pricing + maintenance.
        Schema::table('rest_units', function (Blueprint $table): void {
            $table->string('type')->default('beds')->after('province_id');
            $table->decimal('price', 10, 2)->default(0)->after('type'); // per bed (beds) / per unit (whole_unit)
            $table->string('status')->default('in_service')->after('price'); // whole-unit maintenance
            $table->text('maintenance_note')->nullable()->after('status');
        });

        Schema::table('rest_units', function (Blueprint $table): void {
            $table->dropColumn([
                'single_rooms',
                'single_room_price',
                'double_rooms',
                'double_room_price',
                'triple_rooms',
                'triple_room_price',
            ]);
        });

        // 2) Reusable, global room-type lookup.
        Schema::create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 3) Individual beds belong directly to a rest unit (type = beds).
        Schema::create('rest_unit_beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rest_unit_id')->constrained('rest_units')->cascadeOnDelete();
            $table->string('label');
            $table->string('status')->default('in_service'); // in_service / maintenance
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_started_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 4) Count-based room groups by room type (type = rooms).
        Schema::create('rest_unit_room_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rest_unit_id')->constrained('rest_units')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('maintenance_count')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        // 5) Bookings target a bed / a room group / the whole unit, and support martyr-family beneficiaries.
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            $table->foreignId('rest_unit_bed_id')->nullable()->after('rest_unit_id')->constrained('rest_unit_beds')->nullOnDelete();
            $table->foreignId('rest_unit_room_group_id')->nullable()->after('rest_unit_bed_id')->constrained('rest_unit_room_groups')->nullOnDelete();
            $table->string('beneficiary_type')->default('member')->after('user_id'); // member / martyr_family
            $table->string('beneficiary_name')->nullable()->after('beneficiary_type');
            $table->string('beneficiary_card_number')->nullable()->after('beneficiary_name');
            $table->string('beneficiary_reg_number')->nullable()->after('beneficiary_card_number');
        });
    }

    public function down(): void
    {
        Schema::table('rest_unit_bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rest_unit_bed_id');
            $table->dropConstrainedForeignId('rest_unit_room_group_id');
            $table->dropColumn([
                'beneficiary_type',
                'beneficiary_name',
                'beneficiary_card_number',
                'beneficiary_reg_number',
            ]);
        });

        Schema::dropIfExists('rest_unit_room_groups');
        Schema::dropIfExists('rest_unit_beds');
        Schema::dropIfExists('room_types');

        Schema::table('rest_units', function (Blueprint $table): void {
            $table->unsignedInteger('single_rooms')->default(0);
            $table->decimal('single_room_price', 10, 2)->default(0);
            $table->unsignedInteger('double_rooms')->default(0);
            $table->decimal('double_room_price', 10, 2)->default(0);
            $table->unsignedInteger('triple_rooms')->default(0);
            $table->decimal('triple_room_price', 10, 2)->default(0);
            $table->dropColumn(['type', 'price', 'status', 'maintenance_note']);
        });
    }
};
