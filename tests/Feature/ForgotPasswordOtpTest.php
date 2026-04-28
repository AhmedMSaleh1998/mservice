<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Otp;
use Modules\Users\Models\User;
use Tests\TestCase;

class ForgotPasswordOtpTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/forgot-password-otp.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (! file_exists($this->databasePath)) {
            touch($this->databasePath);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('app.otp_mode', 'live');
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

        DB::purge('sqlite');
        DB::disconnect('sqlite');

        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('otp');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->unique()->nullable();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('otp', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20);
            $table->string('code', 6);
            $table->string('action', 20);
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

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

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_forgot_password_otp_normalizes_country_code_plus_local_phone(): void
    {
        User::query()->create([
            'name' => 'Doctor Test',
            'phone' => '201026513696',
            'email' => 'doctor@example.com',
            'password' => 'secret123',
            'active' => true,
        ]);

        Http::fake([
            'https://sms.example.test/api/SMSSender/SendSMSWithDLR' => Http::response('0', 200),
        ]);

        $response = $this->postJson('/api/v1/otp/send', [
            'phone' => '+2001026513696',
            'action' => 'forgot_password',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('otp', [
            'phone' => '201026513696',
            'action' => 'forget',
        ]);

        Http::assertSent(function ($request): bool {
            return $request['SMSReceiver'] === '201026513696'
                && $request['SMSText'] !== '';
        });
    }
}
