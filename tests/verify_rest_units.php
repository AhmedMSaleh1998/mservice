<?php

use Modules\Services\Services\RestUnitService;

$service = new RestUnitService();

echo "Running Verification Tests...\n";

// Test 1: Province Filter
$res1 = $service->getList(100, ['province_id' => 999]);
echo "Test 1 (Correct Province): Found " . $res1->count() . " units (Expected 1)\n";

$res2 = $service->getList(100, ['province_id' => 998]);
echo "Test 2 (Wrong Province): Found " . $res2->count() . " units (Expected 0)\n";

// Test 2: Room Type Filter
$res3 = $service->getList(100, ['room_type' => 'single_rooms']);
echo "Test 3 (Has Single Rooms): Found " . $res3->count() . " units (Expected 1)\n";

$res4 = $service->getList(100, ['room_type' => 'single_bed']);
echo "Test 4 (Has Single Bed - Count is 0): Found " . $res4->count() . " units (Expected 0)\n";

// Test 3: Availability Filter
// Booked: 2026-02-01 to 2026-02-05
$res5 = $service->getList(100, ['from_date' => '2026-02-02', 'to_date' => '2026-02-03']);
echo "Test 5 (Overlap Date): Found " . $res5->count() . " units (Expected 0)\n";

$res6 = $service->getList(100, ['from_date' => '2025-01-01', 'to_date' => '2026-02-05']); // Overlap end
echo "Test 6 (Overlap End): Found " . $res6->count() . " units (Expected 0)\n";

$res7 = $service->getList(100, ['from_date' => '2026-02-10', 'to_date' => '2026-02-12']);
echo "Test 7 (Free Date): Found " . $res7->count() . " units (Expected 1)\n";

// Test 4: Booking Flow
echo "\nTesting Booking Flow...\n";
$unitId = \Modules\Services\Models\RestUnit::where('province_id', 999)->first()->id;
try {
    // Attempt to book free dates with Unit Type
    $booking = $service->book([
        'rest_unit_id' => $unitId,
        'user_id' => 1,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-04', // 3 Nights
        'unit_type' => 'single_rooms',
    ]);
    // Price should be 100 * 3 = 300
    echo "Booking Created: ID " . $booking->id . ", Status: " . $booking->status . "\n";
    echo "Unit Type: " . $booking->unit_type . ", Total Price: " . $booking->total_price . " (Expected: 300.00)\n";
} catch (\Exception $e) {
    echo "Booking Failed: " . $e->getMessage() . "\n";
}

try {
    // Attempt to book overlapping dates
    $service->book([
        'rest_unit_id' => $unitId,
        'user_id' => 1,
        'start_date' => '2026-03-02', // Overlaps with above
        'end_date' => '2026-03-06',
        'unit_type' => 'double_rooms',
    ]);
    echo "Double Booking Allowed (Fail)\n";
} catch (\Exception $e) {
    echo "Double Booking Prevented (Success): " . $e->getMessage() . "\n";
}

echo "Verification Complete.\n";
