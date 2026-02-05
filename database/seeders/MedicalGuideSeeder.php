<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\MedicalGuide\Models\MedicalSpecialty;

class MedicalGuideSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            'dermatology' => [
                'ar' => 'جلدية وتجميل',
                'en' => 'Dermatology and Cosmetic',
            ],
            'cardiology' => [
                'ar' => 'قلب واوعية دموية',
                'en' => 'Cardiology',
            ],
        ];

        $specialtyModels = [];
        foreach ($specialties as $key => $specialtyName) {
            $specialtyModels[$key] = MedicalSpecialty::query()->create([
                'name' => $specialtyName,
                'is_active' => true,
            ]);
        }

        $doctors = [
            [
                'title' => [
                    'ar' => 'دكتور احمد محمد',
                    'en' => 'Dr. Ahmed Mohamed',
                ],
                'description' => [
                    'ar' => 'استشاري جلدية وتجميل',
                    'en' => 'Dermatology and Cosmetic Consultant',
                ],
                'specialty_id' => $specialtyModels['dermatology']->getKey(),
                'province_id' => 1,
                'is_active' => true,
                'is_featured' => true,
                'places' => [
                    [
                        'name' => [
                            'ar' => 'مستشفى كليوباترا',
                            'en' => 'Cleopatra Hospital',
                        ],
                        'address' => [
                            'ar' => 'شارع التسعين الجنوبي، التجمع الخامس، القاهرة',
                            'en' => 'Teseen St., Fifth Settlement, Cairo',
                        ],
                        'lat' => 30.009398,
                        'lng' => 31.434605,
                        'phones' => ['010012345689', '010012345688'],
                        'is_active' => true,
                    ],
                    [
                        'name' => [
                            'ar' => 'عيادة خاصة',
                            'en' => 'Private Clinic',
                        ],
                        'address' => [
                            'ar' => 'شارع التسعين الجنوبي، التجمع الخامس، القاهرة',
                            'en' => 'Teseen St., Fifth Settlement, Cairo',
                        ],
                        'lat' => 30.008721,
                        'lng' => 31.433921,
                        'phones' => ['010012345687'],
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'title' => [
                    'ar' => 'دكتور عمر حسن',
                    'en' => 'Dr. Omar Hassan',
                ],
                'description' => [
                    'ar' => 'استشاري قلب واوعية دموية',
                    'en' => 'Cardiology Consultant',
                ],
                'specialty_id' => $specialtyModels['cardiology']->getKey(),
                'province_id' => 1,
                'is_active' => true,
                'is_featured' => false,
                'places' => [
                    [
                        'name' => [
                            'ar' => 'مركز القلب الحديث',
                            'en' => 'Modern Heart Center',
                        ],
                        'address' => [
                            'ar' => 'مدينة نصر، القاهرة',
                            'en' => 'Nasr City, Cairo',
                        ],
                        'lat' => 30.056120,
                        'lng' => 31.339865,
                        'phones' => ['010012345699'],
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        foreach ($doctors as $doctorData) {
            $places = $doctorData['places'] ?? [];
            unset($doctorData['places']);

            $primaryPlace = $places[0] ?? null;
            $doctorData['province_id'] = $doctorData['province_id'] ?? ($primaryPlace['province_id'] ?? null);

            $doctor = MedicalGuide::query()->create($doctorData);

            foreach ($places as $place) {
                $doctor->places()->create($place);
            }
        }
    }
}
