<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\MedicalUniversity;

class MedicalUniversitiesSeeder extends Seeder
{
    public function run(): void
    {
        if (MedicalUniversity::query()->exists()) {
            return;
        }

        $items = [
            ['name' => ['ar' => 'جامعة القاهرة', 'en' => 'Cairo University']],
            ['name' => ['ar' => 'جامعة عين شمس', 'en' => 'Ain Shams University']],
            ['name' => ['ar' => 'جامعة الإسكندرية', 'en' => 'Alexandria University']],
            ['name' => ['ar' => 'جامعة المنصورة', 'en' => 'Mansoura University']],
            ['name' => ['ar' => 'جامعة طنطا', 'en' => 'Tanta University']],
            ['name' => ['ar' => 'جامعة أسيوط', 'en' => 'Assiut University']],
            ['name' => ['ar' => 'جامعة الزقازيق', 'en' => 'Zagazig University']],
            ['name' => ['ar' => 'جامعة قناة السويس', 'en' => 'Suez Canal University']],
            ['name' => ['ar' => 'جامعة المنيا', 'en' => 'Minia University']],
            ['name' => ['ar' => 'جامعة جنوب الوادي', 'en' => 'South Valley University']],
            ['name' => ['ar' => 'جامعة بني سويف', 'en' => 'Beni Suef University']],
            ['name' => ['ar' => 'جامعة الفيوم', 'en' => 'Fayoum University']],
            ['name' => ['ar' => 'جامعة المنوفية', 'en' => 'Menoufia University']],
            ['name' => ['ar' => 'جامعة بنها', 'en' => 'Benha University']],
            ['name' => ['ar' => 'جامعة كفر الشيخ', 'en' => 'Kafr El-Sheikh University']],
            ['name' => ['ar' => 'جامعة سوهاج', 'en' => 'Sohag University']],
            ['name' => ['ar' => 'جامعة بورسعيد', 'en' => 'Port Said University']],
            ['name' => ['ar' => 'جامعة دمياط', 'en' => 'Damietta University']],
            ['name' => ['ar' => 'جامعة أسوان', 'en' => 'Aswan University']],
            ['name' => ['ar' => 'جامعة حلوان', 'en' => 'Helwan University']],
            ['name' => ['ar' => 'جامعة الأزهر', 'en' => 'Al-Azhar University']],
        ];

        foreach ($items as $item) {
            MedicalUniversity::create($item);
        }
    }
}
