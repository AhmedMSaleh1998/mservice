<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Religion;

class ReligionsSeeder extends Seeder
{
    public function run(): void
    {
        if (Religion::query()->exists()) {
            return;
        }

        $items = [
            ['name' => ['ar' => 'مسلم', 'en' => 'Muslim']],
            ['name' => ['ar' => 'مسيحي', 'en' => 'Christian']],
            ['name' => ['ar' => 'يهودي', 'en' => 'Judaism']],
            ['name' => ['ar' => 'غير ذلك', 'en' => 'other']],
        ];

        foreach ($items as $item) {
            Religion::create($item);
        }
    }
}
