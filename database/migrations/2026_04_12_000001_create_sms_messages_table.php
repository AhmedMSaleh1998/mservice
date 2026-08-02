<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('community_sms');
            $table->string('type', 50)->nullable();
            $table->string('message_id', 100)->nullable()->unique();
            $table->string('sender', 50)->nullable();
            $table->string('receiver', 30)->nullable()->index();
            $table->text('message');
            $table->string('status', 50)->default('pending')->index();
            $table->string('provider_status', 100)->nullable()->index();
            $table->integer('response_status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->json('metadata')->nullable();
            $table->json('dlr_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
