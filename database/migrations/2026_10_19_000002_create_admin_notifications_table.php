<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Notifications an admin sends from the dashboard over Firebase and/or SMS.
        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('channels');
            $table->string('audience', 30);
            $table->json('user_ids')->nullable();
            $table->json('registration_numbers')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('fcm_sent_count')->default(0);
            $table->unsignedInteger('fcm_failed_count')->default(0);
            $table->unsignedInteger('sms_sent_count')->default(0);
            $table->unsignedInteger('sms_failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('batch_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
