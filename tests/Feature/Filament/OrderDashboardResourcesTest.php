<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DeliveryRequests\Pages\ListDeliveryRequests;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\ViewTransaction;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Models\Province;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderDashboardResourcesTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/order-dashboard-resources.sqlite');

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

        foreach (['ViewAny:Order', 'View:Order', 'Update:Order'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo(['ViewAny:Order', 'View:Order', 'Update:Order']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::auth()->login($admin);
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::disconnect('sqlite');

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_transactions_pages_show_service_and_gateway_details(): void
    {
        PaymentMethod::query()->create([
            'name' => ['en' => 'Fawry', 'ar' => 'فوري'],
            'key' => 'fawry',
            'is_active' => true,
        ]);

        $certificateOrder = $this->createCertificateOrder('Professional Standing', [
            'status' => 'paid_successfully',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'CERT-1001',
            'gateway_reference' => 'FW-778899',
            'gateway_status' => 'PAID',
            'payload' => [
                'pricing' => [
                    'currency' => 'EGP',
                    'items' => [
                        [
                            'code' => 'certificate_printing',
                            'label' => 'Certificate printing',
                            'unit_price' => '100.00',
                            'quantity' => 1,
                            'amount' => '100.00',
                        ],
                        [
                            'code' => 'certificate_shipping',
                            'label' => 'Shipping fees',
                            'unit_price' => '25.00',
                            'quantity' => 1,
                            'amount' => '25.00',
                        ],
                    ],
                    'subtotal' => '125.00',
                    'discount' => '0.00',
                    'fees' => '0.00',
                    'total' => '125.00',
                ],
            ],
        ]);

        $membershipOrder = $this->createMembershipOrder('Dr. Ayman Farid');

        Livewire::test(ListTransactions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$certificateOrder, $membershipOrder])
            ->assertTableActionExists('view', null, $certificateOrder)
            ->assertSee('Professional Standing')
            ->assertSee('Membership ID Card');

        Livewire::test(ViewTransaction::class, ['record' => $certificateOrder->getKey()])
            ->assertSuccessful()
            ->assertSee('Transaction Summary')
            ->assertSee('Service Details')
            ->assertSee('Pricing Breakdown')
            ->assertSee('Professional Standing')
            ->assertSee('Shipping fees')
            ->assertSee('CERT-1001')
            ->assertSee('FW-778899');
    }

    public function test_delivery_requests_list_filters_to_physical_deliveries_and_updates_status(): void
    {
        $membershipOrder = $this->createMembershipOrder('Dr. Mona Adel');
        $deliveryCertificateOrder = $this->createCertificateOrder('Board Certificate');
        $this->createCertificateOrder('Digital Eligibility Letter', [
            'status' => 'pending_payment',
        ], 'digital');

        Livewire::test(ListDeliveryRequests::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$membershipOrder, $deliveryCertificateOrder])
            ->assertTableActionExists('updateDeliveryStatus', null, $deliveryCertificateOrder)
            ->assertTableBulkActionExists('updateSelectedDeliveryStatuses')
            ->assertDontSee('Digital Eligibility Letter')
            ->callTableAction('updateDeliveryStatus', $deliveryCertificateOrder, data: [
                'delivery_status' => CertificateRequest::DELIVERY_STATUS_SHIPPED,
            ])
            ->callTableBulkAction('updateSelectedDeliveryStatuses', [$membershipOrder, $deliveryCertificateOrder], [
                'delivery_status' => MembershipRequest::DELIVERY_STATUS_DELIVERED,
            ]);

        $this->assertSame(
            MembershipRequest::DELIVERY_STATUS_DELIVERED,
            $deliveryCertificateOrder->orderable->fresh()->delivery_status
        );

        $this->assertSame(
            MembershipRequest::DELIVERY_STATUS_DELIVERED,
            $membershipOrder->orderable->fresh()->delivery_status
        );
    }

    private function createCertificateOrder(string $certificateName, array $orderAttributes = [], string $deliveryMethod = 'delivery'): Order
    {
        $user = $this->createUser('Certificate User');
        $address = $this->createAddress($user);

        $certificate = Certificate::query()->create([
            'name' => ['en' => $certificateName, 'ar' => 'شهادة'],
            'description' => ['en' => 'Certificate', 'ar' => 'شهادة'],
            'price' => 100,
            'is_active' => true,
        ]);

        $request = CertificateRequest::query()->create([
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'delivery_method' => $deliveryMethod,
            'user_address_id' => $deliveryMethod === 'delivery' ? $address->id : null,
            'phone' => '01000000000',
            'email' => 'doctor@example.com',
            'status' => $orderAttributes['status'] ?? 'pending_payment',
            'delivery_status' => $deliveryMethod === 'delivery' ? CertificateRequest::DELIVERY_STATUS_PENDING : null,
            'printing_cost' => 100,
            'delivery_cost' => $deliveryMethod === 'delivery' ? 25 : 0,
            'subscription_cost' => 0,
            'total_amount' => $deliveryMethod === 'delivery' ? 125 : 100,
        ]);

        return $request->order()->create(array_merge([
            'user_id' => $user->id,
            'amount' => $request->total_amount,
            'currency' => 'EGP',
            'status' => $request->status,
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'CERT-' . fake()->unique()->numberBetween(1000, 9999),
            'gateway_reference' => null,
            'gateway_status' => null,
            'checkout_url' => null,
            'payload' => [],
            'payment_started_at' => now(),
            'payment_last_synced_at' => now(),
            'paid_at' => null,
        ], $orderAttributes))->fresh(['user', 'orderable']);
    }

    private function createMembershipOrder(string $fullName): Order
    {
        Service::query()->firstOrCreate(
            ['key' => 'membership-id'],
            [
                'title' => ['en' => 'Membership ID Card', 'ar' => 'كارنيه العضوية'],
                'description' => ['en' => 'Membership card', 'ar' => 'كارنيه العضوية'],
                'service_type_id' => null,
                'price' => 80,
                'is_active' => true,
                'is_featured' => false,
            ]
        );

        $user = $this->createUser($fullName);
        $address = $this->createAddress($user);

        $request = MembershipRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => $fullName,
            'specialty' => 'Dentist',
            'degree' => 'Bachelor',
            'registration_number' => 'REG-' . fake()->numberBetween(100, 999),
            'delivery_method' => 'delivery',
            'status' => 'pending_payment',
            'delivery_status' => MembershipRequest::DELIVERY_STATUS_PENDING,
            'printing_cost' => 80,
            'delivery_cost' => 20,
            'subscription_cost' => 0,
            'total_amount' => 100,
            'user_address_id' => $address->id,
            'payment_method' => 'cash',
        ]);

        return $request->order()->create([
            'user_id' => $user->id,
            'amount' => 100,
            'currency' => 'EGP',
            'status' => 'pending_payment',
            'payment_method' => 'cash',
            'provider' => 'manual',
            'merchant_ref_num' => 'MID-' . fake()->unique()->numberBetween(1000, 9999),
            'gateway_reference' => null,
            'gateway_status' => null,
            'checkout_url' => null,
            'payload' => [],
            'payment_started_at' => now(),
            'payment_last_synced_at' => now(),
            'paid_at' => null,
        ])->fresh(['user', 'orderable']);
    }

    private function createUser(string $name): User
    {
        return User::query()->create([
            'name' => $name,
            'phone' => '01012345678',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret123',
            'national_id' => (string) fake()->unique()->numberBetween(10000000000000, 99999999999999),
            'reg_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'active' => true,
            'notification_enabled' => true,
        ]);
    }

    private function createAddress(User $user): UserAddress
    {
        $province = Province::query()->first() ?? Province::query()->create([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 25,
        ]);

        return UserAddress::query()->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'district' => 'Nasr City',
            'street' => 'Makram Ebeid',
            'phone' => '01011111111',
            'unit_number' => '12B',
            'address_name' => 'Clinic',
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

        Schema::connection('sqlite')->create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('province_id')->nullable();
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->string('phone')->nullable();
            $table->string('unit_number')->nullable();
            $table->string('address_name')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('services', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('key')->unique();
            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('certificate_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('certificate_id')->nullable();
            $table->string('delivery_method')->default('delivery');
            $table->unsignedBigInteger('user_address_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('delivery_status')->nullable();
            $table->decimal('printing_cost', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('subscription_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('membership_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('specialty')->nullable();
            $table->string('degree')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('delivery_method')->default('delivery');
            $table->json('address')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('delivery_status')->nullable();
            $table->decimal('printing_cost', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('subscription_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('user_address_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();
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
