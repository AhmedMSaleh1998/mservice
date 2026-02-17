<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\ServicesSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\ReligionsSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\LookupExcelSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        //User::factory()->create([
        //    'name' => 'Test User',
        //    'email' => 'test@example.com',
        //]);

        $this->call([
            LookupExcelSeeder::class,
            LanguagesSeeder::class,
            ReligionsSeeder::class,
            PaymentMethodsSeeder::class,
            RolesSeeder::class,
            ServicesSeeder::class,
        ]);
    }
}
