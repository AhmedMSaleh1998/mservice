<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Models\RestUnit;
use Modules\Core\Models\Province;
use Modules\Services\Models\RestUnitBooking;

class RestUnitVerificationSeeder extends Seeder
{
    public function run()
    {
        // Cleanup previous test data
        RestUnit::where('province_id', 999)->forceDelete();
        Province::where('id', 999)->delete();

        // Ensure we have a province
        $province = Province::firstOrCreate(['id' => 999], ['name' => ['en' => 'Test Province', 'ar' => 'محافظة تجريبية']]);

        // Create a Rest Unit
        $unit = RestUnit::create([
            'name' => ['en' => 'Test Rest Unit', 'ar' => 'استراحة تجريبية'],
            'address' => ['en' => '123 Test St', 'ar' => '123 شارع تجريبي'],
            'province_id' => $province->id,
            'single_rooms' => 5,
            'double_rooms' => 2,
            'single_bed' => 0,
            'is_active' => true,
        ]);

        // Create a Booking (Overlap Scenario)
        RestUnitBooking::create([
            'rest_unit_id' => $unit->id,
            'user_id' => 1,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-05',
            'status' => 'active',
        ]);
        
        $this->command->info('Verification data seeded.');
        $this->command->info('Unit ID: ' . $unit->id . ' in Province ID: ' . $province->id);
        $this->command->info('Booked from 2026-02-01 to 2026-02-05');
    }
}
