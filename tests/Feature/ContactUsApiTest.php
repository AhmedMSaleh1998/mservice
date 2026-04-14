<?php

namespace Tests\Feature;

use App\Models\ContactInfo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactUsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('contact_infos');

        Schema::create('contact_infos', function (Blueprint $table) {
            $table->id();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->json('phones')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('fax')->nullable();
            $table->timestamps();
        });
    }

    public function test_show_returns_contact_info_with_whatsapp_number(): void
    {
        ContactInfo::query()->create([
            'address' => [
                'ar' => 'المقر الرئيسي',
                'en' => 'Main office',
            ],
            'email' => 'info@example.com',
            'phones' => ['01000000000', '01111111111'],
            'whatsapp' => '201234567890',
            'fax' => '0223456789',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/contact-us');

        $response->assertOk();
        $response->assertJsonPath('status', 200);
        $response->assertJsonPath('data.address', 'Main office');
        $response->assertJsonPath('data.email', 'info@example.com');
        $response->assertJsonPath('data.phones.0', '01000000000');
        $response->assertJsonPath('data.phones.1', '01111111111');
        $response->assertJsonPath('data.whatsapp', '201234567890');
        $response->assertJsonPath('data.fax', '0223456789');
    }
}
