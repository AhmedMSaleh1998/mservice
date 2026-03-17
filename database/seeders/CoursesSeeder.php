<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoursesSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'title' => [
                    'ar' => 'المؤتمر الثالث عشر للجمعية العربية للتغذية والعلاجيه الصحية 1',
                    'en' => '13th Annual Conference of the Arabic Society for Healthy & Therapeutic Nutrition'
                ],
                'description' => [
                    'ar' => 'في إطار الاستعدادات الجارية لانتخابات التجديد النصفي لنقابة الأطباء...',
                    'en' => 'As part of the ongoing preparations for the midterm elections of the Medical Syndicate...'
                ],
                'start_date' => '2026-02-22',
                'end_date' => '2026-02-25',
                'price' => 4000.00,
                'available_count' => 200,
                'type' => 'attend',
                'is_active' => true,
            ],

            [
                'title' => [
                    'ar' => ' 2المؤتمر الثالث عشر للجمعية العربية للتغذية والعلاجيه الصحية',
                    'en' => '13th Annual Conference of the Arabic Society for Healthy & Therapeutic Nutrition'
                ],
                'description' => [
                    'ar' => 'دورة اونلاين مكثفة حول أحدث أساليب التغذية العلاجية.',
                    'en' => 'An intensive online course about the latest therapeutic nutrition methods.'
                ],
                'start_date' => '2026-03-22',
                'end_date' => '2026-03-24',
                'price' => 0.00,
                'available_count' => 200,
                'type' => 'online',
                'is_active' => true,
            ],

            [
                'title' => [
                    'ar' => 'المؤتمر الثالث عشر للجمعية العربية للتغذية والعلاجيه الصحية3',
                    'en' => '13th Annual Conference of the Arabic Society for Healthy & Therapeutic Nutrition'
                ],
                'description' => [
                    'ar' => 'دورة تجمع بين الحضور والاونلاين مع محاضرات مباشرة.',
                    'en' => 'A hybrid course combining in-person and online sessions with live lectures.'
                ],
                'start_date' => '2026-05-22',
                'end_date' => '2026-05-25',
                'price' => 4000.00,
                'available_count' => 200,
                'type' => 'hybrid',
                'is_active' => true,
            ],
        ];

        foreach ($courses as $course) {
            DB::table('courses')->insert([
                'title'       => json_encode($course['title'], JSON_UNESCAPED_UNICODE),
                'description' => json_encode($course['description'], JSON_UNESCAPED_UNICODE),
                'start_date'  => $course['start_date'],
                'end_date'    => $course['end_date'],
                'price'       => $course['price'],
                'available_count' => $course['available_count'],
                'type'        => $course['type'],
                'is_active'   => $course['is_active'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
