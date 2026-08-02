<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CertificateRequests\CertificateRequestResource;
use App\Filament\Resources\CertificateRequests\Pages\ListCertificateRequests;
use App\Filament\Resources\CertificateRequests\Pages\ViewCertificateRequest;
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
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CertificateRequestResourceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/certificate-request-resource.sqlite');

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

    public function test_resource_registers_view_page_and_exposes_view_and_bulk_actions(): void
    {
        $request = $this->createCertificateRequest();

        $this->assertArrayHasKey('view', CertificateRequestResource::getPages());

        Livewire::test(ListCertificateRequests::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$request])
            ->assertTableActionExists('view', null, $request)
            ->assertTableActionExists('edit', null, $request)
            ->assertTableBulkActionExists('updateStatus')
            ->assertTableBulkActionExists('updateDeliveryStatus');
    }

    public function test_view_page_displays_certificate_request_details_and_payment_payload(): void
    {
        PaymentMethod::query()->create([
            'name' => ['en' => 'Fawry', 'ar' => 'فوري'],
            'key' => 'fawry',
            'is_active' => true,
        ]);

        $request = $this->createCertificateRequest([
            'phone' => '01099998888',
            'email' => 'john@example.com',
            'status' => CertificateRequest::STATUS_PAID_SUCCESSFULLY,
            'delivery_status' => CertificateRequest::DELIVERY_STATUS_PENDING,
            'total_amount' => 350,
        ], [
            'certificate_name' => ['en' => 'Single Certificate', 'ar' => 'شهادة واحد'],
        ]);

        $this->createOrder($request, [
            'amount' => 350,
            'status' => CertificateRequest::STATUS_PAID_SUCCESSFULLY,
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'CERT-REQ-1',
            'gateway_reference' => 'FW-778899',
            'gateway_status' => 'PAID',
            'payload' => [
                'charge_request' => [
                    'merchantRefNum' => 'CERT-REQ-1',
                    'amount' => '350.00',
                ],
                'charge_response' => [
                    'referenceNumber' => 'FW-778899',
                    'statusCode' => 200,
                ],
            ],
        ]);

        Livewire::test(ViewCertificateRequest::class, ['record' => $request->getKey()])
            ->assertSuccessful()
            ->assertSee('Certificate Request')
            ->assertSee('John Doe')
            ->assertSee('Single Certificate')
            ->assertSee('Delivery')
            ->assertSee('Paid Successfully')
            ->assertSee('Fawry')
            ->assertSee('CERT-REQ-1')
            ->assertSee('FW-778899')
            ->assertSee('charge_request')
            ->assertSee('charge_response');
    }

    public function test_bulk_actions_can_update_status_and_delivery_status(): void
    {
        $deliveryRequest = $this->createCertificateRequest([
            'status' => CertificateRequest::STATUS_PENDING_PAYMENT,
            'delivery_method' => 'delivery',
            'delivery_status' => CertificateRequest::DELIVERY_STATUS_PENDING,
        ], [
            'certificate_name' => ['en' => 'Delivery Certificate', 'ar' => 'شهادة توصيل'],
        ]);

        $digitalRequest = $this->createCertificateRequest([
            'status' => CertificateRequest::STATUS_PENDING_PAYMENT,
            'delivery_method' => 'digital',
            'delivery_status' => null,
        ], [
            'certificate_name' => ['en' => 'Digital Certificate', 'ar' => 'شهادة رقمية'],
        ]);

        Livewire::test(ListCertificateRequests::class)
            ->callTableBulkAction('updateStatus', [$deliveryRequest, $digitalRequest], [
                'status' => CertificateRequest::STATUS_PROCESSING,
            ]);

        $this->assertSame(CertificateRequest::STATUS_PROCESSING, $deliveryRequest->fresh()->status);
        $this->assertSame(CertificateRequest::STATUS_PROCESSING, $digitalRequest->fresh()->status);

        Livewire::test(ListCertificateRequests::class)
            ->callTableBulkAction('updateDeliveryStatus', [$deliveryRequest, $digitalRequest], [
                'delivery_status' => CertificateRequest::DELIVERY_STATUS_SHIPPED,
            ]);

        $this->assertSame(CertificateRequest::DELIVERY_STATUS_SHIPPED, $deliveryRequest->fresh()->delivery_status);
        $this->assertNull($digitalRequest->fresh()->delivery_status);
    }

    private function createCertificateRequest(array $attributes = [], array $options = []): CertificateRequest
    {
        $user = $this->createUser();
        $address = $this->createAddress($user);
        $certificate = $this->createCertificate($options['certificate_name'] ?? ['en' => 'Certificate One', 'ar' => 'شهادة واحد']);

        return CertificateRequest::query()->create(array_merge([
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'delivery_method' => 'delivery',
            'user_address_id' => $address->id,
            'phone' => '01012345678',
            'email' => 'john@example.com',
            'status' => CertificateRequest::STATUS_PENDING_PAYMENT,
            'delivery_status' => CertificateRequest::DELIVERY_STATUS_PENDING,
            'printing_cost' => 200,
            'delivery_cost' => 50,
            'subscription_cost' => 100,
            'total_amount' => 350,
        ], $attributes));
    }

    private function createOrder(CertificateRequest $request, array $attributes = []): Order
    {
        return $request->order()->create(array_merge([
            'user_id' => $request->user_id,
            'amount' => $request->total_amount,
            'currency' => 'EGP',
            'status' => $request->status,
            'payment_method' => null,
            'provider' => null,
            'merchant_ref_num' => null,
            'gateway_reference' => null,
            'gateway_status' => null,
            'checkout_url' => null,
            'payload' => [],
            'payment_started_at' => now(),
            'payment_last_synced_at' => now(),
            'paid_at' => now(),
        ], $attributes));
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'John Doe',
            'phone' => '01012345678',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret123',
            'national_id' => (string) fake()->unique()->numberBetween(10000000000000, 99999999999999),
            'reg_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));
    }

    private function createAddress(User $user, array $attributes = []): UserAddress
    {
        $province = Province::query()->create([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 50,
        ]);

        return UserAddress::query()->create(array_merge([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'district' => 'Nasr City',
            'street' => 'Makram Ebeid',
            'phone' => '01012345678',
            'unit_number' => '12A',
            'address_name' => 'Home',
        ], $attributes));
    }

    private function createCertificate(array $name): Certificate
    {
        return Certificate::query()->create([
            'name' => $name,
            'description' => ['en' => 'Certificate Description', 'ar' => 'وصف الشهادة'],
            'price' => 200,
            'is_active' => true,
            'order' => 1,
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

        Schema::connection('sqlite')->create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
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
            $table->string('status')->default(CertificateRequest::STATUS_PENDING_PAYMENT);
            $table->string('delivery_status')->nullable();
            $table->decimal('printing_cost', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('subscription_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
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
