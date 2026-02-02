<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceType;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        $types = ServiceType::orderBy('id')->get();

        if ($types->isEmpty()) {
            $defaultTypes = [
                'administrative' => [
                    'ar' => 'خدمات إدارية',
                    'en' => 'Administrative Services',
                ],
                'travel' => [
                    'ar' => 'خدمات الرحلات',
                    'en' => 'Travel Services',
                ],
                'accommodation' => [
                    'ar' => 'خدمات الإقامة',
                    'en' => 'Accommodation Services',
                ],
            ];

            foreach ($defaultTypes as $typeName) {
                ServiceType::create(['name' => $typeName]);
            }

            $types = ServiceType::orderBy('id')->get();
        }

        $types = $types->values();
        $typeIndexByServiceKey = [
            'ads' => 0,
            'certificate' => 0,
            'documents-procedures' => 0,
            'membership-id' => 0,
            'travels' => 1,
            'rest-house' => 2,
        ];

        $services = [
            [
                'key' => 'courses',
                'title' => [
                    'ar' => 'الكورسات',
                    'en' => 'Courses',
                ],
            ],
            [
                'key' => 'medical_guide',
                'title' => [
                    'ar' => 'الدليل الطبي',
                    'en' => 'Medical Guide',
                ],
            ],
            [
                'key' => 'membership-id',
                'title' => [
                    'ar' => 'اشتخراج كارنية عضوية',
                    'en' => 'Membership ID',
                ],
            ],
            [
                'key' => 'certificate',
                'title' => [
                    'ar' => 'الشهادات',
                    'en' => 'Certificate',
                ],
            ],
            [
                'key' => 'ads',
                'title' => [
                    'ar' => 'الاعلانات',
                    'en' => 'Ads',
                ],
            ],
            [
                'key' => 'rest-house',
                'title' => [
                    'ar' => 'اماكن الاقامة',
                    'en' => 'Rest House',
                ],
            ],
            [
                'key' => 'travels',
                'title' => [
                    'ar' => 'الرحلات',
                    'en' => 'Travels',
                ],
            ],
            [
                'key' => 'documents-procedures',
                'title' => [
                    'ar' => 'المستندات والاجراءات',
                    'en' => 'Documents and Procedures',
                ],
            ],
        ];

        $iconPath = public_path('assets/medical-syndicate-logo.png');

        foreach ($services as $service) {
            $typeIndex = $typeIndexByServiceKey[$service['key']] ?? 0;
            $serviceTypeId = $types->get($typeIndex)?->id;

            $model = Service::updateOrCreate(
                ['key' => $service['key']],
                [
                    'title' => $service['title'],
                    'description' => $service['title'],
                    'service_type_id' => $serviceTypeId,
                    'is_featured' => false,
                    'is_active' => true,
                ]
            );

            if (is_file($iconPath) && ! $model->getFirstMedia('icon')) {
                $model->addMedia($iconPath)
                    ->preservingOriginal()
                    ->toMediaCollection('icon');
            }
        }
    }
}
