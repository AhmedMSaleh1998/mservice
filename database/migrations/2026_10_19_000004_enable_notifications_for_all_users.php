<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Push notifications never existed before, so the old false default was
        // never a user's choice. Turn them on for everyone; from now on a user
        // who switches them off in the app stops receiving push notifications.
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notification_enabled')->default(true)->change();
        });

        DB::table('users')->update(['notification_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notification_enabled')->default(false)->change();
        });
    }
};
