<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Nationality;

class NationalitiesSeeder extends Seeder
{
    public function run(): void
    {
        if (Nationality::query()->exists()) {
            return;
        }

        $items = [
            ['name' => ['ar' => 'مصر', 'en' => 'Egypt']],
            ['name' => ['ar' => 'المملكة العربية السعودية', 'en' => 'Saudi Arabia']],
            ['name' => ['ar' => 'الإمارات العربية المتحدة', 'en' => 'United Arab Emirates']],
            ['name' => ['ar' => 'الكويت', 'en' => 'Kuwait']],
            ['name' => ['ar' => 'الأردن', 'en' => 'Jordan']],
            ['name' => ['ar' => 'السودان', 'en' => 'Sudan']],
            ['name' => ['ar' => 'ليبيا', 'en' => 'Libya']],
            ['name' => ['ar' => 'فلسطين', 'en' => 'Palestine']],
            ['name' => ['ar' => 'سوريا', 'en' => 'Syria']],
            ['name' => ['ar' => 'العراق', 'en' => 'Iraq']],
            ['name' => ['ar' => 'اليمن', 'en' => 'Yemen']],
            ['name' => ['ar' => 'لبنان', 'en' => 'Lebanon']],
            ['name' => ['ar' => 'قطر', 'en' => 'Qatar']],
            ['name' => ['ar' => 'البحرين', 'en' => 'Bahrain']],
            ['name' => ['ar' => 'عُمان', 'en' => 'Oman']],
        ];

        foreach ($items as $item) {
            Nationality::create($item);
        }
    }
}
