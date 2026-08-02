<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\SmsMessage;
use Tests\TestCase;

class CommunitySmsDlrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sms_messages');

        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('community_sms');
            $table->string('type', 50)->nullable();
            $table->string('message_id', 100)->nullable()->unique();
            $table->string('sender', 50)->nullable();
            $table->string('receiver', 30)->nullable();
            $table->text('message');
            $table->string('status', 50)->default('pending');
            $table->string('provider_status', 100)->nullable();
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

    public function test_dlr_callback_updates_sms_message_status(): void
    {
        $message = SmsMessage::query()->create([
            'provider' => 'community_sms',
            'type' => 'otp',
            'message_id' => 'sms-123',
            'sender' => 'CommunityAD',
            'receiver' => '201026513696',
            'message' => 'Verification code',
            'status' => 'accepted',
            'sent_at' => now(),
        ]);

        $response = $this->postJson(route('api.sms.community.dlr'), [
            'userSMSId' => 'sms-123',
            'dlrResponseStatus' => 'Delivered',
            'mobile' => '201026513696',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => true,
            'message' => 'SMS delivery report received.',
        ]);

        $message->refresh();

        $this->assertSame('delivered', $message->status);
        $this->assertSame('Delivered', $message->provider_status);
        $this->assertSame('201026513696', $message->receiver);
        $this->assertNotNull($message->delivered_at);
        $this->assertSame('sms-123', data_get($message->dlr_payload, 'userSMSId'));
    }

    public function test_dlr_callback_creates_message_when_record_does_not_exist(): void
    {
        $response = $this->postJson(route('api.sms.community.dlr'), [
            'SMSID' => 'sms-new',
            'Status' => 'Failed',
            'SMSReceiver' => '201000000000',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('sms_messages', [
            'message_id' => 'sms-new',
            'receiver' => '201000000000',
            'status' => 'failed',
            'provider_status' => 'Failed',
        ]);
    }
}
