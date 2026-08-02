<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Province;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\RoomType;

class RestUnitVerificationSeeder extends Seeder
{
    public function run()
    {
        // Cleanup previous test data
        RestUnit::where('province_id', 999)->forceDelete();
        Province::where('id', 999)->delete();

        $province = Province::firstOrCreate(['id' => 999], ['name' => ['en' => 'Test Province', 'ar' => 'محافظة تجريبية']]);

        // --- Type 1: beds (individual) ---
        $bedsUnit = RestUnit::create([
            'name' => ['en' => 'Beds Rest Unit', 'ar' => 'استراحة أسرّة'],
            'address' => ['en' => '123 Test St', 'ar' => '123 شارع تجريبي'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_BEDS,
            'price' => 100.00,
            'is_active' => true,
        ]);
        for ($i = 1; $i <= 6; $i++) {
            $bedsUnit->beds()->create([
                'label' => "Bed {$i}",
                'status' => $i === 6 ? 'maintenance' : 'in_service',
            ]);
        }

        RestUnitBooking::create([
            'rest_unit_id' => $bedsUnit->id,
            'rest_unit_bed_id' => $bedsUnit->beds()->first()->id,
            'user_id' => 1,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-05',
            'status' => RestUnitBooking::STATUS_PAID_SUCCESSFULLY,
            'total_price' => 400,
        ]);

        // --- Type 2: rooms ---
        $family = RoomType::firstOrCreate(['id' => 9991], ['name' => ['en' => 'Family Room', 'ar' => 'غرفة عائلية']]);
        $suite = RoomType::firstOrCreate(['id' => 9992], ['name' => ['en' => 'Suite', 'ar' => 'سويت']]);

        $roomsUnit = RestUnit::create([
            'name' => ['en' => 'Rooms Rest Unit', 'ar' => 'استراحة غرف'],
            'address' => ['en' => '456 Test St', 'ar' => '456 شارع تجريبي'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_ROOMS,
            'is_active' => true,
        ]);
        for ($i = 1; $i <= 6; $i++) {
            $roomsUnit->rooms()->create([
                'room_type_id' => $family->id,
                'name' => "Family {$i}",
                'price' => 750,
                'status' => $i === 6 ? 'maintenance' : 'in_service',
            ]);
        }
        for ($i = 1; $i <= 3; $i++) {
            $roomsUnit->rooms()->create([
                'room_type_id' => $suite->id,
                'name' => "Suite {$i}",
                'price' => 1200,
                'status' => 'in_service',
            ]);
        }

        // --- Type 3: whole unit ---
        RestUnit::create([
            'name' => ['en' => 'Chalet 7', 'ar' => 'شاليه ٧'],
            'address' => ['en' => '789 Test St', 'ar' => '789 شارع تجريبي'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_WHOLE_UNIT,
            'price' => 2500,
            'status' => RestUnit::STATUS_IN_SERVICE,
            'is_active' => true,
        ]);

        $this->command->info('Verification data seeded: beds / rooms / whole-unit rest units in Province ' . $province->id);
    }
}
