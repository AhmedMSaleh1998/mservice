<?php

namespace Tests\Unit\Modules\Core\Services;

use App\Services\Sms\CommunitySmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Otp;
use Modules\Core\Services\OtpService;
use RuntimeException;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('otp');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('otp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('phone', 20);
            $table->string('code', 6);
            $table->boolean('is_used')->default(false);
            $table->string('action', 20);
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function test_generate_phone_otp_expires_otp_when_sms_dispatch_fails(): void
    {
        config()->set('app.otp_mode', 'live');

        $otpService = new OtpService(new class extends CommunitySmsService {
            public function sendOtp(string $phone, string $code, array $options = []): array
            {
                throw new RuntimeException('SMS provider is unavailable.');
            }
        });

        try {
            $otpService->generatePhoneOtp('01026513696', 'register');
            $this->fail('Expected SMS failure to bubble up.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMS provider is unavailable.', $exception->getMessage());
        }

        $otp = Otp::query()->latest('id')->first();

        $this->assertNotNull($otp);
        $this->assertTrue($otp->expired_at->lte(now()));
        $this->assertNull(Otp::query()->findValidByPhone('01026513696', 'register')->first());
    }

    public function test_generate_phone_otp_stores_normalized_phone(): void
    {
        config()->set('app.otp_mode', 'test');

        $otp = app(OtpService::class)->generatePhoneOtp('+2001026513696', 'forget');

        $this->assertSame('201026513696', $otp->phone);
        $this->assertDatabaseHas('otp', [
            'phone' => '201026513696',
            'action' => 'forget',
            'code' => '111111',
        ]);
    }

    public function test_verify_phone_otp_accepts_equivalent_phone_formats(): void
    {
        config()->set('app.otp_mode', 'test');

        app(OtpService::class)->generatePhoneOtp('01026513696', 'forget');

        $this->assertTrue(app(OtpService::class)->verifyPhoneOtp('+2001026513696', '111111', 'forget'));
    }

    public function test_generate_phone_otp_returns_clear_throttle_message(): void
    {
        config()->set('app.otp_mode', 'test');
        app()->setLocale('en');

        app(OtpService::class)->generatePhoneOtp('01026513696', 'register');

        try {
            app(OtpService::class)->generatePhoneOtp('+2001026513696', 'register');
            $this->fail('Expected OTP throttle validation exception.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['phone'][0] ?? '';

            $this->assertStringStartsWith('A verification code has already been sent.', $message);
            $this->assertStringContainsString('seconds', $message);
        }
    }
}
