<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\ServicesSeeder;
use Database\Seeders\EgyptProvincesSeeder;
use Database\Seeders\NationalitiesSeeder;
use Database\Seeders\MedicalUniversitiesSeeder;
use Database\Seeders\GradesSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\ReligionsSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesSeeder;

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
            EgyptProvincesSeeder::class,
            NationalitiesSeeder::class,
            MedicalUniversitiesSeeder::class,
            GradesSeeder::class,
            LanguagesSeeder::class,
            ReligionsSeeder::class,
            PaymentMethodsSeeder::class,
            RolesSeeder::class,
            ServicesSeeder::class,
        ]);
    }
}
