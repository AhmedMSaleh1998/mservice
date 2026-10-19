<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Each user's in-app notification inbox.
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_notification_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'admin_notification_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
