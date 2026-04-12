<?php

namespace Tests\Unit\Modules\Core\Services;

use App\Services\Sms\CommunitySmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
}
