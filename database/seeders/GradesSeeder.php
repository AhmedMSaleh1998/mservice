<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Grade;

class GradesSeeder extends Seeder
{
    public function run(): void
    {
        if (Grade::query()->exists()) {
            return;
        }

        $items = [
            ['name' => ['ar' => 'امتياز', 'en' => 'Excellent']],
            ['name' => ['ar' => 'جيد جدا', 'en' => 'Very Good']],
            ['name' => ['ar' => 'جيد', 'en' => 'Good']],
            ['name' => ['ar' => 'مقبول', 'en' => 'Pass']],
        ];

        foreach ($items as $item) {
            Grade::create($item);
        }
    }
}
