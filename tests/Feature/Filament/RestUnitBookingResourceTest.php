<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ResetUnits\ResetUnitResource;
use App\Filament\Resources\RestUnitBookings\Pages\ListRestUnitBookings;
use App\Filament\Resources\RestUnitBookings\Pages\ViewRestUnitBooking;
use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Models\Province;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RestUnitBookingResourceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/rest-unit-booking-resource.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        touch($this->databasePath);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('cache.default', 'array');
        config()->set('permission.cache.store', 'array');
        config()->set('session.driver', 'array');

        DB::purge('sqlite');
        DB::disconnect('sqlite');

        $this->createTables();

        app()->setLocale('en');
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Admin::query()->create([
            'name' => 'Manage Admin',
            'email' => 'manage-admin@example.com',
            'password' => 'password',
            'active' => true,
        ]);

        $permissions = [
            'ViewAny:RestUnitBooking',
            'View:RestUnitBooking',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::auth()->login($admin);
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_resource_registers_view_page_and_list_exposes_view_action(): void
    {
        $booking = $this->createBooking();

        $this->assertArrayHasKey('view', RestUnitBookingResource::getPages());

        Livewire::test(ListRestUnitBookings::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$booking])
            ->assertTableActionExists('view', null, $booking);
    }

    public function test_resource_is_nested_under_rest_units_inside_services_navigation(): void
    {
        $this->assertNull(RestUnitBookingResource::getNavigationGroup());
        $this->assertSame('Bookings', RestUnitBookingResource::getNavigationLabel());
        $this->assertSame(ResetUnitResource::getNavigationLabel(), RestUnitBookingResource::getNavigationParentItem());
    }

    public function test_view_page_displays_booking_payment_summary_and_raw_gateway_data(): void
    {
        PaymentMethod::query()->create([
            'name' => ['en' => 'InstaPay', 'ar' => 'انستاباي'],
            'key' => 'instapay',
            'is_active' => true,
        ]);

        $booking = $this->createBooking([
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-14',
            'total_price' => 24690,
        ]);

        $this->createOrder($booking, [
            'amount' => 24690,
            'currency' => 'EGP',
            'status' => 'checkout_pending',
            'payment_method' => 'instapay',
            'provider' => 'manual',
            'merchant_ref_num' => 'RUB-REF-1',
            'gateway_reference' => '778899',
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://payments.example.test/orders/1',
            'payload' => [
                'pricing' => [
                    'currency' => 'EGP',
                    'items' => [
                        [
                            'code' => 'rest_unit_stay',
                            'label' => 'Stay fees',
                            'description' => 'Al Ainy Rest House - Single room (6 Nights)',
                            'unit_price' => '4000.00',
                            'quantity' => 6,
                            'amount' => '24000.00',
                        ],
                        [
                            'code' => 'subscription_fees',
                            'unit_price' => '690.00',
                            'quantity' => 1,
                            'amount' => '690.00',
                            'meta' => [
                                'subscription_years' => 3,
                            ],
                        ],
                    ],
                    'subtotal' => '24690.00',
                    'discount' => '0.00',
                    'fees' => '0.00',
                    'total' => '24690.00',
                ],
                'subscription_charge' => [
                    'register_no' => '12345',
                    'amount' => '690.00',
                    'years' => 3,
                    'status' => 0,
                ],
                'charge_request' => [
                    'merchantRefNum' => 'RUB-REF-1',
                    'amount' => '24690.00',
                ],
                'charge_response' => [
                    'referenceNumber' => '778899',
                    'statusCode' => 200,
                ],
            ],
        ]);

        Livewire::test(ViewRestUnitBooking::class, ['record' => $booking->getKey()])
            ->assertSuccessful()
            ->assertSee('Booking Summary')
            ->assertSee('Pending Payment')
            ->assertSee('Single room')
            ->assertSee('Rest Unit User')
            ->assertSee('Al Ainy Rest House')
            ->assertSee('Payment / Order Details')
            ->assertSee('InstaPay')
            ->assertSee('Checkout Pending')
            ->assertSee('Pricing Breakdown')
            ->assertSee('Stay fees')
            ->assertSee('Subscription fees')
            ->assertSee('Charge Request')
            ->assertSee('Charge Response')
            ->assertSee('Payload')
            ->assertSee('RUB-REF-1')
            ->assertSee('778899');
    }

    public function test_view_page_handles_missing_order_gracefully(): void
    {
        $booking = $this->createBooking();

        Livewire::test(ViewRestUnitBooking::class, ['record' => $booking->getKey()])
            ->assertSuccessful()
            ->assertSee('No payment order has been linked yet.');
    }

    public function test_view_page_shows_placeholders_when_order_payload_is_missing(): void
    {
        $booking = $this->createBooking();

        $this->createOrder($booking, [
            'payload' => null,
        ]);

        Livewire::test(ViewRestUnitBooking::class, ['record' => $booking->getKey()])
            ->assertSuccessful()
            ->assertSee('No pricing breakdown available.')
            ->assertSee('No subscription snapshot available.')
            ->assertSee('No gateway payload available.');
    }

    private function createBooking(array $attributes = []): RestUnitBooking
    {
        $user = $this->createUser();
        $restUnit = $this->createRestUnit();

        return RestUnitBooking::query()->create(array_merge([
            'rest_unit_id' => $restUnit->id,
            'user_id' => $user->id,
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-10',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'total_price' => 8000,
        ], $attributes));
    }

    private function createOrder(RestUnitBooking $booking, array $attributes = []): Order
    {
        return $booking->order()->create(array_merge([
            'user_id' => $booking->user_id,
            'amount' => $booking->total_price,
            'currency' => 'EGP',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'payment_method' => null,
            'provider' => null,
            'merchant_ref_num' => null,
            'gateway_reference' => null,
            'gateway_status' => null,
            'checkout_url' => null,
            'payload' => [],
            'payment_started_at' => now(),
            'payment_last_synced_at' => now(),
            'paid_at' => null,
        ], $attributes));
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Rest Unit User',
            'phone' => '01012345678',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret123',
            'national_id' => (string) fake()->unique()->numberBetween(10000000000000, 99999999999999),
            'reg_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));
    }

    private function createRestUnit(array $attributes = []): RestUnit
    {
        $province = Province::query()->create([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 0,
        ]);

        return RestUnit::query()->create(array_merge([
            'name' => ['en' => 'Al Ainy Rest House', 'ar' => 'استراحة القصر العيني'],
            'address' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
            'province_id' => $province->id,
            'single_rooms' => 2,
            'double_rooms' => 1,
            'triple_rooms' => 1,
            'is_active' => true,
            'single_room_price' => 4000,
            'double_room_price' => 5000,
            'triple_room_price' => 6000,
        ], $attributes));
    }

    private function createTables(): void
    {
        Schema::connection('sqlite')->create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('sqlite')->create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('sqlite')->create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::connection('sqlite')->create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::connection('sqlite')->create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

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
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('rest_units', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('address')->nullable();
            $table->unsignedBigInteger('province_id');
            $table->unsignedInteger('single_rooms')->default(0);
            $table->decimal('single_room_price', 10, 2)->default(0);
            $table->unsignedInteger('double_rooms')->default(0);
            $table->decimal('double_room_price', 10, 2)->default(0);
            $table->unsignedInteger('triple_rooms')->default(0);
            $table->decimal('triple_room_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('rest_unit_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rest_unit_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('unit_type')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending_payment');
            $table->decimal('total_price', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('key')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('orderable_type');
            $table->unsignedBigInteger('orderable_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->string('status')->default('pending_payment');
            $table->string('payment_method')->nullable();
            $table->string('provider')->nullable();
            $table->string('merchant_ref_num')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('gateway_status')->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('payload')->nullable();
            $table->timestamp('payment_started_at')->nullable();
            $table->timestamp('payment_last_synced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }
}
