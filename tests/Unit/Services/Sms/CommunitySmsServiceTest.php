<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\CommunitySmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\SmsMessage;
use Tests\TestCase;

class CommunitySmsServiceTest extends TestCase
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

        config()->set('services.community_sms', [
            'enabled' => true,
            'endpoint' => 'https://sms.example.test/api/SMSSender/SendSMSWithDLR',
            'username' => 'Emuapp.com',
            'password' => 'secret',
            'sender' => 'CommunityAD',
            'lang' => 'a',
            'dlr_url' => 'https://app.example.test/dlr',
            'default_country_code' => '20',
            'normalize_receivers' => true,
            'connect_timeout' => 5,
            'timeout' => 20,
            'otp_lang' => 'a',
            'otp_dlr_url' => 'https://app.example.test/otp-dlr',
        ]);
    }

    public function test_send_posts_expected_payload_to_community_sms_api(): void
    {
        Http::fake([
            'https://sms.example.test/api/SMSSender/SendSMSWithDLR' => Http::response('0', 200),
        ]);

        $result = app(CommunitySmsService::class)->send('01026513696', 'Welcome to EMS', [
            'message_id' => 'sms-123',
        ]);

        $this->assertSame(0, $result['status_code']);
        $this->assertSame('sms-123', $result['message_id']);
        $this->assertSame('201026513696', $result['receiver']);
        $this->assertDatabaseHas('sms_messages', [
            'message_id' => 'sms-123',
            'receiver' => '201026513696',
            'status' => 'accepted',
            'type' => 'generic',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sms.example.test/api/SMSSender/SendSMSWithDLR'
                && $request['UserName'] === 'Emuapp.com'
                && $request['Password'] === 'secret'
                && $request['SMSText'] === 'Welcome to EMS'
                && $request['SMSLang'] === 'a'
                && $request['SMSSender'] === 'CommunityAD'
                && $request['SMSReceiver'] === '201026513696'
                && $request['SMSID'] === 'sms-123'
                && $request['DLRURL'] === 'https://app.example.test/dlr';
        });
    }

    public function test_send_otp_uses_otp_specific_defaults(): void
    {
        Http::fake([
            'https://sms.example.test/api/SMSSender/SendSMSWithDLR' => Http::response('0', 200),
        ]);

        app(CommunitySmsService::class)->sendOtp('+201026513696', '123456', [
            'message_id' => 'otp-123',
        ]);

        Http::assertSent(function ($request): bool {
            return $request['SMSText'] === 'Your verification code is 123456'
                && $request['SMSReceiver'] === '201026513696'
                && $request['SMSID'] === 'otp-123'
                && $request['DLRURL'] === 'https://app.example.test/otp-dlr';
        });

        $message = SmsMessage::query()->where('message_id', 'otp-123')->first();

        $this->assertNotNull($message);
        $this->assertSame('otp', $message->type);
    }

    public function test_send_removes_local_trunk_prefix_after_country_code(): void
    {
        Http::fake([
            'https://sms.example.test/api/SMSSender/SendSMSWithDLR' => Http::response('0', 200),
        ]);

        $result = app(CommunitySmsService::class)->sendOtp('+2001026513696', '123456', [
            'message_id' => 'otp-country-code-local-prefix',
        ]);

        $this->assertSame('201026513696', $result['receiver']);

        Http::assertSent(function ($request): bool {
            return $request['SMSReceiver'] === '201026513696'
                && $request['SMSID'] === 'otp-country-code-local-prefix';
        });
    }

    public function test_send_uses_local_dlr_route_when_configured_url_is_missing(): void
    {
        config()->set('services.community_sms.dlr_url', null);
        config()->set('services.community_sms.otp_dlr_url', null);

        Http::fake([
            'https://sms.example.test/api/SMSSender/SendSMSWithDLR' => Http::response('0', 200),
        ]);

        app(CommunitySmsService::class)->send('01026513696', 'Welcome to EMS', [
            'message_id' => 'sms-local-dlr',
        ]);

        Http::assertSent(function ($request): bool {
            return $request['SMSID'] === 'sms-local-dlr'
                && $request['DLRURL'] === route('api.sms.community.dlr');
        });
    }
}
