<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table): void {
            $table->string('edit_link_token')->nullable()->after('oracle_register_no');
            $table->timestamp('edit_link_sent_at')->nullable()->after('edit_link_token');
            $table->timestamp('edit_link_opened_at')->nullable()->after('edit_link_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'edit_link_token',
                'edit_link_sent_at',
                'edit_link_opened_at',
            ]);
        });
    }
};
