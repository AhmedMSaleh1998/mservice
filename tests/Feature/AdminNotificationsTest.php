<?php

namespace Tests\Feature;

use App\Services\Sms\CommunitySmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Notifications\Models\AdminNotification;
use Modules\Notifications\Models\DeviceToken;
use Modules\Notifications\Models\UserNotification;
use Modules\Notifications\Services\AdminNotificationService;
use Modules\Notifications\Services\FcmService;
use Modules\Users\Models\User;
use RuntimeException;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    private string $databasePath;

    /** @var array<int, array{tokens: array<int, string>, title: string, body: string, data: array}> */
    private array $pushes = [];

    /** @var array<int, string> */
    private array $smsPhones = [];

    /** @var array<int, string> */
    private array $invalidTokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/admin-notifications.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        touch($this->databasePath);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('queue.default', 'sync');

        DB::purge('sqlite');
        DB::disconnect('sqlite');

        $this->createTables();
        $this->fakeChannels();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_device_token_is_saved_and_moves_to_the_account_that_signs_in(): void
    {
        $first = $this->createUser(['phone' => '01011111111']);
        $second = $this->createUser(['phone' => '01022222222']);

        Sanctum::actingAs($first);
        $this->withHeaders(['deviceId' => 'device-1'])
            ->postJson('/api/v1/device-tokens', ['token' => 'token-abc', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $first->id,
            'token' => 'token-abc',
            'platform' => 'android',
            'device_id' => 'device-1',
        ]);

        Sanctum::actingAs($second);
        $this->postJson('/api/v1/device-tokens', ['token' => 'token-abc', 'platform' => 'android'])->assertOk();

        $this->assertSame(1, DeviceToken::query()->count());
        $this->assertDatabaseHas('device_tokens', ['user_id' => $second->id, 'token' => 'token-abc']);

        $this->deleteJson('/api/v1/device-tokens', ['token' => 'token-abc'])->assertOk();
        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_inbox_lists_notifications_and_marks_them_read(): void
    {
        $user = $this->createUser();
        $other = $this->createUser(['phone' => '01033333333']);
        $older = $this->createNotification(['title' => 'Older']);
        $newer = $this->createNotification(['title' => 'Newer']);

        foreach ([$older, $newer] as $notification) {
            UserNotification::query()->create(['user_id' => $user->id, 'admin_notification_id' => $notification->id]);
        }
        UserNotification::query()->create(['user_id' => $other->id, 'admin_notification_id' => $older->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $newer->id)
            ->assertJsonPath('data.items.0.title', 'Newer')
            ->assertJsonPath('data.items.0.is_read', false)
            ->assertJsonPath('data.unread_count', 2);

        $this->postJson("/api/v1/notifications/{$newer->id}/read")->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);

        // The other user's inbox is untouched.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $other->id, 'read_at' => null]);
    }

    public function test_sending_fills_inboxes_and_delivers_push_and_sms_to_active_users(): void
    {
        $pushUser = $this->createUser(['phone' => '01011111111']);
        $pushOffUser = $this->createUser(['phone' => '01022222222', 'notification_enabled' => false]);
        $inactiveUser = $this->createUser(['phone' => '01033333333', 'active' => false]);

        DeviceToken::query()->create(['user_id' => $pushUser->id, 'token' => 'token-on']);
        DeviceToken::query()->create(['user_id' => $pushOffUser->id, 'token' => 'token-off']);
        DeviceToken::query()->create(['user_id' => $inactiveUser->id, 'token' => 'token-inactive']);

        $notification = $this->createNotification([
            'channels' => [AdminNotification::CHANNEL_FCM, AdminNotification::CHANNEL_SMS],
            'audience' => AdminNotification::AUDIENCE_ALL,
        ]);

        app(AdminNotificationService::class)->send($notification);

        // Push only reaches users who kept notifications on; SMS is official
        // communication and goes to every active user.
        $this->assertSame(['token-on'], $this->pushes[0]['tokens']);
        $this->assertSame((string) $notification->id, (string) $this->pushes[0]['data']['notification_id']);
        $this->assertEqualsCanonicalizing(['01011111111', '01022222222'], $this->smsPhones);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $pushUser->id, 'admin_notification_id' => $notification->id]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $pushOffUser->id, 'admin_notification_id' => $notification->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $inactiveUser->id]);

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $notification->id,
            'status' => AdminNotification::STATUS_COMPLETED,
            'recipients_count' => 2,
            'fcm_sent_count' => 1,
            'fcm_failed_count' => 0,
            'sms_sent_count' => 2,
            'sms_failed_count' => 0,
        ]);
    }

    public function test_registration_numbers_audience_reaches_only_the_listed_users(): void
    {
        $listed = $this->createUser(['phone' => '01011111111', 'reg_number' => '12345']);
        $this->createUser(['phone' => '01022222222', 'reg_number' => '67890']);

        $service = app(AdminNotificationService::class);
        $notification = $this->createNotification([
            'channels' => [AdminNotification::CHANNEL_SMS],
            'audience' => AdminNotification::AUDIENCE_REGISTRATION_NUMBERS,
            // Arabic digits, mixed separators and blanks, as an admin may paste them.
            'registration_numbers' => $service->parseRegistrationNumbers("١٢٣٤٥\n, 99999  "),
        ]);

        $service->send($notification);

        $this->assertSame(['01011111111'], $this->smsPhones);
        $this->assertSame([$listed->id], UserNotification::query()->pluck('user_id')->all());
    }

    public function test_failed_sms_are_counted_and_invalid_push_tokens_are_dropped(): void
    {
        $user = $this->createUser(['phone' => '01011111111']);
        $this->createUser(['phone' => 'reject-me']);
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'token-expired']);
        $this->invalidTokens = ['token-expired'];

        $notification = $this->createNotification([
            'channels' => [AdminNotification::CHANNEL_FCM, AdminNotification::CHANNEL_SMS],
        ]);

        app(AdminNotificationService::class)->send($notification);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'token-expired']);
        $this->assertDatabaseHas('admin_notifications', [
            'id' => $notification->id,
            'fcm_sent_count' => 0,
            'fcm_failed_count' => 1,
            'sms_sent_count' => 1,
            'sms_failed_count' => 1,
        ]);
    }

    public function test_a_notification_is_only_sent_once(): void
    {
        $this->createUser(['phone' => '01011111111']);
        $notification = $this->createNotification(['channels' => [AdminNotification::CHANNEL_SMS]]);
        $service = app(AdminNotificationService::class);

        $service->send($notification);
        $service->send($notification->fresh());

        $this->assertCount(1, $this->smsPhones);
    }

    private function fakeChannels(): void
    {
        $test = $this;

        app()->instance(FcmService::class, new class($test) extends FcmService {
            public function __construct(private readonly AdminNotificationsTest $test)
            {
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function send(array $tokens, string $title, string $body, array $data = []): array
            {
                return $this->test->recordPush($tokens, $title, $body, $data);
            }
        });

        app()->instance(CommunitySmsService::class, new class($test) extends CommunitySmsService {
            public function __construct(private readonly AdminNotificationsTest $test)
            {
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function send(string $phone, string $message, array $options = []): array
            {
                return $this->test->recordSms($phone);
            }
        });
    }

    public function recordPush(array $tokens, string $title, string $body, array $data): array
    {
        $this->pushes[] = compact('tokens', 'title', 'body', 'data');
        $invalid = array_values(array_intersect($tokens, $this->invalidTokens));

        return [
            'sent' => count($tokens) - count($invalid),
            'failed' => count($invalid),
            'invalid_tokens' => $invalid,
        ];
    }

    public function recordSms(string $phone): array
    {
        if ($phone === 'reject-me') {
            throw new RuntimeException('Community SMS rejected message with status code [-18].');
        }

        $this->smsPhones[] = $phone;

        return ['status_code' => 0];
    }

    private function createUser(array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create(array_merge([
            'name' => "User {$sequence}",
            'phone' => '0100000000' . $sequence,
            'email' => "user{$sequence}@example.com",
            'password' => bcrypt('secret123'),
            'national_id' => '2990101123456' . $sequence,
            'reg_number' => (string) (10000 + $sequence),
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));
    }

    private function createNotification(array $attributes = []): AdminNotification
    {
        return AdminNotification::query()->create(array_merge([
            'title' => 'Syndicate notice',
            'body' => 'The general assembly meets on Friday.',
            'channels' => [AdminNotification::CHANNEL_FCM],
            'audience' => AdminNotification::AUDIENCE_ALL,
            'status' => AdminNotification::STATUS_DRAFT,
        ], $attributes));
    }

    private function createTables(): void
    {
        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('national_id')->nullable();
            $table->string('reg_number')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('active')->default(true);
            $table->string('lang')->nullable();
            $table->boolean('notification_enabled')->default(true);
            $table->string('address')->nullable();
            $table->string('neqaba_address')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('admin_notifications', function (Blueprint $table): void {
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('batch_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('admin_notification_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'admin_notification_id']);
        });

        Schema::connection('sqlite')->create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }
}
