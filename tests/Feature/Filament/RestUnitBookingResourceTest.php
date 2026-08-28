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
use Modules\Services\Models\RestUnitRoom;
use Modules\Services\Models\RoomType;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RestUnitTestSchema;
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

        foreach (['ViewAny:RestUnitBooking', 'View:RestUnitBooking'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo(['ViewAny:RestUnitBooking', 'View:RestUnitBooking']);

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
            // The list opens filtered to paid bookings; clear it to see all.
            ->filterTable('status', null)
            ->assertCanSeeTableRecords([$booking])
            ->assertTableActionExists('view', null, $booking);
    }

    public function test_cash_martyr_booking_is_marked_paid_and_member_cleared(): void
    {
        $data = RestUnitBookingResource::applyBookingDefaults([
            'beneficiary_type' => RestUnitBooking::BENEFICIARY_MARTYR_FAMILY,
            'user_id' => 5,
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'payment_method' => RestUnitBooking::PAYMENT_CASH,
            'beneficiary_card_number' => '29901011234567',
            'payment_reference' => 'TRX-999',
            'total_price' => 1500,
        ]);

        $this->assertNull($data['user_id']);
        $this->assertSame(RestUnitBooking::STATUS_PAID_SUCCESSFULLY, $data['status']);
        $this->assertNotNull($data['paid_at']);
        $this->assertSame('29901011234567', $data['beneficiary_card_number']);
    }

    public function test_amount_is_calculated_from_nightly_price_times_nights_times_units(): void
    {
        $room = $this->createRoomsUnitRoom(); // price 4000
        $unit = $room->restUnit;
        $room2 = $unit->rooms()->create(['room_type_id' => $room->room_type_id, 'name' => 'Room 2', 'price' => 5000, 'status' => 'in_service']);

        // Rooms: sum of selected room prices × nights. 3 nights, 4000 + 5000 = 9000 → 27000.
        $rooms = RestUnitBookingResource::calculateAmount('2026-08-01', '2026-08-04', $unit->id, [$room->id, $room2->id], []);
        $this->assertSame(27000.0, $rooms);

        // Beds: unit price × nights × bed count.
        $bedsUnit = RestUnit::query()->create([
            'name' => ['en' => 'Beds', 'ar' => 'أسرّة'],
            'address' => ['en' => 'x', 'ar' => 'x'],
            'province_id' => $unit->province_id,
            'type' => RestUnit::TYPE_BEDS,
            'price' => 100,
            'is_active' => true,
        ]);
        $b1 = $bedsUnit->beds()->create(['label' => 'Bed 1', 'status' => 'in_service']);
        $b2 = $bedsUnit->beds()->create(['label' => 'Bed 2', 'status' => 'in_service']);

        // 2 nights × 100 × 2 beds = 400.
        $beds = RestUnitBookingResource::calculateAmount('2026-08-01', '2026-08-03', $bedsUnit->id, [], [$b1->id, $b2->id]);
        $this->assertSame(400.0, $beds);

        // No dates → 0.
        $this->assertSame(0.0, RestUnitBookingResource::calculateAmount(null, null, $unit->id, [$room->id], []));
    }

    public function test_fawry_booking_stays_pending(): void
    {
        $data = RestUnitBookingResource::applyBookingDefaults([
            'beneficiary_type' => RestUnitBooking::BENEFICIARY_MEMBER,
            'user_id' => 5,
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'payment_method' => RestUnitBooking::PAYMENT_FAWRY,
        ]);

        $this->assertSame(5, $data['user_id']);
        $this->assertSame(RestUnitBooking::STATUS_PENDING_PAYMENT, $data['status']);
        $this->assertNull($data['paid_at']);
    }

    public function test_replicate_for_extra_units_creates_one_booking_per_unit(): void
    {
        $room = $this->createRoomsUnitRoom();
        $unit = $room->restUnit;
        $room2 = $unit->rooms()->create(['room_type_id' => $room->room_type_id, 'name' => 'Room 2', 'price' => 4000, 'status' => 'in_service']);
        $room3 = $unit->rooms()->create(['room_type_id' => $room->room_type_id, 'name' => 'Room 3', 'price' => 4000, 'status' => 'in_service']);
        $user = $this->createUser();

        $main = RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $room->id,
            'user_id' => $user->id,
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-10',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'total_price' => 8000,
        ]);

        RestUnitBookingResource::replicateForExtraUnits($main, 'rest_unit_room_id', [$room2->id, $room3->id]);

        $this->assertSame(3, RestUnitBooking::query()->where('rest_unit_id', $unit->id)->count());
        $this->assertDatabaseHas('rest_unit_bookings', ['rest_unit_room_id' => $room2->id, 'total_price' => 0, 'user_id' => $user->id]);
        $this->assertDatabaseHas('rest_unit_bookings', ['rest_unit_room_id' => $room3->id, 'total_price' => 0, 'user_id' => $user->id]);
    }

    public function test_occupied_units_and_availability_guard_prevent_double_booking(): void
    {
        $room = $this->createRoomsUnitRoom();
        $unit = $room->restUnit;
        $room2 = $unit->rooms()->create(['room_type_id' => $room->room_type_id, 'name' => 'Room 2', 'price' => 4000, 'status' => 'in_service']);
        $user = $this->createUser();

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $room->id,
            'user_id' => $user->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'status' => RestUnitBooking::STATUS_PAID_SUCCESSFULLY,
            'total_price' => 4000,
        ]);

        // Room 1 is occupied for an overlapping window; room 2 is free.
        $occupied = RestUnitBookingResource::occupiedUnitIds($unit->id, 'rest_unit_room_id', '2026-08-05', '2026-08-08');
        $this->assertContains($room->id, $occupied);
        $this->assertNotContains($room2->id, $occupied);

        // Guard rejects re-booking the occupied room in the same period.
        try {
            RestUnitBookingResource::assertUnitsAvailable([
                'rest_unit_id' => $unit->id,
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-08',
                'rest_unit_room_ids' => [$room->id],
            ]);
            $this->fail('Expected a validation exception for the double booking.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('rest_unit_room_ids', $e->errors());
        }

        // A non-overlapping period is allowed.
        RestUnitBookingResource::assertUnitsAvailable([
            'rest_unit_id' => $unit->id,
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-12',
            'rest_unit_room_ids' => [$room->id],
        ]);
        $this->assertTrue(true);
    }

    public function test_cancel_records_reason_and_flags_online_refund(): void
    {
        $onlineBooking = $this->createBooking();
        $this->createOrder($onlineBooking, [
            'status' => 'paid_successfully',
            'payment_method' => 'fawry',
            'gateway_status' => 'PAID',
            'paid_at' => now(),
        ]);
        $onlineBooking->load('order');

        $this->assertTrue($onlineBooking->requiresOnlineRefund());

        $onlineBooking->cancel('Guest changed plans');
        $this->assertSame(RestUnitBooking::STATUS_CANCELLED, $onlineBooking->fresh()->status);
        $this->assertSame('Guest changed plans', $onlineBooking->fresh()->cancellation_reason);
        $this->assertNotNull($onlineBooking->fresh()->cancelled_at);

        // Offline (martyr-family, no online order) needs no online refund.
        $offlineBooking = $this->createBooking(['beneficiary_type' => RestUnitBooking::BENEFICIARY_MARTYR_FAMILY]);
        $this->assertFalse($offlineBooking->requiresOnlineRefund());
    }

    public function test_resolve_selected_units_prefers_rooms_then_beds(): void
    {
        $this->assertSame(['col' => 'rest_unit_room_id', 'ids' => [3, 5]], RestUnitBookingResource::resolveSelectedUnits(['rest_unit_room_ids' => [3, 5]]));
        $this->assertSame(['col' => 'rest_unit_bed_id', 'ids' => [7]], RestUnitBookingResource::resolveSelectedUnits(['rest_unit_bed_ids' => [7]]));
        $this->assertSame(['col' => null, 'ids' => []], RestUnitBookingResource::resolveSelectedUnits([]));
    }

    public function test_bank_transfer_member_booking_is_marked_paid(): void
    {
        $data = RestUnitBookingResource::applyBookingDefaults([
            'beneficiary_type' => RestUnitBooking::BENEFICIARY_MEMBER,
            'user_id' => 5,
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'payment_method' => RestUnitBooking::PAYMENT_BANK_TRANSFER,
        ]);

        $this->assertSame(5, $data['user_id']);
        $this->assertSame(RestUnitBooking::STATUS_PAID_SUCCESSFULLY, $data['status']);
        $this->assertNotNull($data['paid_at']);
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
                            'meta' => ['subscription_years' => 3],
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
                'charge_request' => ['merchantRefNum' => 'RUB-REF-1', 'amount' => '24690.00'],
                'charge_response' => ['referenceNumber' => '778899', 'statusCode' => 200],
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

        $this->createOrder($booking, ['payload' => null]);

        Livewire::test(ViewRestUnitBooking::class, ['record' => $booking->getKey()])
            ->assertSuccessful()
            ->assertSee('No pricing breakdown available.')
            ->assertSee('No subscription snapshot available.')
            ->assertSee('No gateway payload available.');
    }

    public function test_checkout_day_is_free_for_the_next_booking(): void
    {
        $booking = $this->createBooking(); // stays 2026-04-08 -> 2026-04-10
        $roomId = $booking->rest_unit_room_id;
        $unitId = $booking->rest_unit_id;

        // Same-day handover: a stay starting on the previous checkout day is free.
        $this->assertSame([], RestUnitBookingResource::occupiedUnitIds($unitId, 'rest_unit_room_id', '2026-04-10', '2026-04-12'));

        // A stay ending on the existing check-in day is free too.
        $this->assertSame([], RestUnitBookingResource::occupiedUnitIds($unitId, 'rest_unit_room_id', '2026-04-06', '2026-04-08'));

        // Real overlaps still block.
        $this->assertSame([$roomId], RestUnitBookingResource::occupiedUnitIds($unitId, 'rest_unit_room_id', '2026-04-09', '2026-04-11'));
        $this->assertSame([$roomId], RestUnitBookingResource::occupiedUnitIds($unitId, 'rest_unit_room_id', '2026-04-07', '2026-04-09'));

        // A single-day range means that night: blocked during the stay, free on checkout day.
        $this->assertSame([$roomId], RestUnitBookingResource::occupiedUnitIds($unitId, 'rest_unit_room_id', '2026-04-09', '2026-04-09'));
        $this->assertSame([], RestUnitBookingResource::occupiedUnitIds($unitId, 'rest_unit_room_id', '2026-04-10', '2026-04-10'));
    }

    private function createBooking(array $attributes = []): RestUnitBooking
    {
        $user = $this->createUser();
        $room = $this->createRoomsUnitRoom();

        return RestUnitBooking::query()->create(array_merge([
            'rest_unit_id' => $room->rest_unit_id,
            'rest_unit_room_id' => $room->id,
            'user_id' => $user->id,
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-10',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
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

    private function createRoomsUnitRoom(): RestUnitRoom
    {
        $province = Province::query()->create([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 0,
        ]);

        $unit = RestUnit::query()->create([
            'name' => ['en' => 'Al Ainy Rest House', 'ar' => 'استراحة القصر العيني'],
            'address' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_ROOMS,
            'is_active' => true,
        ]);

        $roomType = RoomType::query()->create(['name' => ['en' => 'Single room', 'ar' => 'غرفة فردية']]);

        return $unit->rooms()->create([
            'room_type_id' => $roomType->id,
            'name' => 'Room 1',
            'price' => 4000,
            'status' => 'in_service',
        ]);
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

        RestUnitTestSchema::create('sqlite');

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

        Schema::connection('sqlite')->create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->text('manipulations');
            $table->text('custom_properties');
            $table->text('generated_conversions');
            $table->text('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->nullableTimestamps();
        });
    }
}
