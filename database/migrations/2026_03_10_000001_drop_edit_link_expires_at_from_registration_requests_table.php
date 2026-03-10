<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('registration_requests', 'edit_link_expires_at')) {
                $table->dropColumn('edit_link_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('registration_requests', 'edit_link_expires_at')) {
                $table->timestamp('edit_link_expires_at')->nullable()->after('edit_link_sent_at');
            }
        });
    }
};
