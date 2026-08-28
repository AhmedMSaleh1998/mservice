<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Province;

class ProvinceEnglishNamesSeeder extends Seeder
{
    /**
     * Fills the English translation of each province, matched by its stable
     * syndicate code. Arabic names are left untouched. Idempotent — safe to
     * re-run; it overwrites the 'en' translation from this curated map.
     */
    private const ENGLISH_NAMES_BY_CODE = [
        0 => 'Not Specified',
        1 => 'Cairo',
        2 => 'Alexandria',
        3 => 'Port Said',
        4 => 'Suez',
        5 => 'Damietta',
        6 => 'Dakahlia',
        7 => 'Sharqia',
        8 => 'Qalyubia',
        9 => 'Kafr El Sheikh',
        10 => 'Gharbia',
        11 => 'Monufia',
        12 => 'Beheira',
        13 => 'Ismailia',
        14 => 'Giza',
        15 => 'Beni Suef',
        16 => 'Fayoum',
        17 => 'Minya',
        18 => 'Assiut',
        19 => 'Sohag',
        20 => 'Qena',
        21 => 'Aswan',
        22 => 'Red Sea',
        23 => 'New Valley',
        24 => 'Matrouh',
        25 => 'North Sinai',
        26 => 'South Sinai',
        27 => 'Outside Egypt',
        28 => 'Medical Professions Union',
        29 => 'Helwan',
        30 => '6th of October',
        31 => 'Luxor',
        33 => 'Electronic Payment',
        99 => 'General Syndicate',
        1003 => 'Outside Egypt',
    ];

    public function run(): void
    {
        foreach (self::ENGLISH_NAMES_BY_CODE as $code => $englishName) {
            $provinces = Province::query()->where('code', $code)->get();

            foreach ($provinces as $province) {
                $province->setTranslation('name', 'en', $englishName);
                $province->save();
            }
        }
    }
}
