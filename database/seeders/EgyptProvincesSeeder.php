<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EgyptProvincesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = [
            ['name' => ['ar' => 'القاهرة', 'en' => 'Cairo']],
            ['name' => ['ar' => 'الجيزة', 'en' => 'Giza']],
            ['name' => ['ar' => 'الإسكندرية', 'en' => 'Alexandria']],
            ['name' => ['ar' => 'الدقهلية', 'en' => 'Dakahlia']],
            ['name' => ['ar' => 'البحر الأحمر', 'en' => 'Red Sea']],
            ['name' => ['ar' => 'البحيرة', 'en' => 'Beheira']],
            ['name' => ['ar' => 'الفيوم', 'en' => 'Fayoum']],
            ['name' => ['ar' => 'الغربية', 'en' => 'Gharbia']],
            ['name' => ['ar' => 'الإسماعيلية', 'en' => 'Ismailia']],
            ['name' => ['ar' => 'المنوفية', 'en' => 'Monufia']],
            ['name' => ['ar' => 'المنيا', 'en' => 'Minya']],
            ['name' => ['ar' => 'القليوبية', 'en' => 'Qalyubia']],
            ['name' => ['ar' => 'الوادي الجديد', 'en' => 'New Valley']],
            ['name' => ['ar' => 'شمال سيناء', 'en' => 'North Sinai']],
            ['name' => ['ar' => 'بورسعيد', 'en' => 'Port Said']],
            ['name' => ['ar' => 'قنا', 'en' => 'Qena']],
            ['name' => ['ar' => 'سوهاج', 'en' => 'Sohag']],
            ['name' => ['ar' => 'السويس', 'en' => 'Suez']],
            ['name' => ['ar' => 'أسوان', 'en' => 'Aswan']],
            ['name' => ['ar' => 'أسيوط', 'en' => 'Assiut']],
            ['name' => ['ar' => 'بني سويف', 'en' => 'Beni Suef']],
            ['name' => ['ar' => 'دمياط', 'en' => 'Damietta']],
            ['name' => ['ar' => 'جنوب سيناء', 'en' => 'South Sinai']],
            ['name' => ['ar' => 'كفر الشيخ', 'en' => 'Kafr El Sheikh']],
            ['name' => ['ar' => 'مطروح', 'en' => 'Matrouh']],
            ['name' => ['ar' => 'الأقصر', 'en' => 'Luxor']],
            ['name' => ['ar' => 'الشرقية', 'en' => 'Sharqia']],
        ];

        foreach ($provinces as $key => $province) {
            $provinces[$key]['name'] = json_encode($province['name']);
        }
        DB::table('provinces')->insert($provinces);
    }
}
