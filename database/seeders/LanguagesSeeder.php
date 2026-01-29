<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Language;

class LanguagesSeeder extends Seeder
{
    public function run(): void
    {
        if (Language::query()->exists()) {
            return;
        }

        $items = [
            ['name' => ['ar' => 'العربية', 'en' => 'Arabic']],
            ['name' => ['ar' => 'الإنجليزية', 'en' => 'English']],
            ['name' => ['ar' => 'الفرنسية', 'en' => 'French']],
            ['name' => ['ar' => 'الألمانية', 'en' => 'German']],
            ['name' => ['ar' => 'الإيطالية', 'en' => 'Italian']],
            ['name' => ['ar' => 'الإسبانية', 'en' => 'Spanish']],
        ];

        foreach ($items as $item) {
            Language::create($item);
        }
    }
}
